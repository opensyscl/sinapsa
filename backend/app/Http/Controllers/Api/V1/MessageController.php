<?php

namespace App\Http\Controllers\Api\V1;

use App\Channels\Enums\MessageDirection;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Jobs\SendOutboundMessage;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MessageController extends Controller
{
    /**
     * Enviar mensaje desde la bandeja humana hacia el contacto.
     * Crea Message en status='queued' y dispatchea SendOutboundMessage.
     *
     * Solo soporta texto y plantilla por ahora — la API pública con scopes,
     * Idempotency-Key, etc. la haremos en Fase 5 con su propio token.
     */
    public function sendInConversation(Conversation $conversation, Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:text,template'],
            'body' => ['required_if:type,text', 'nullable', 'string', 'max:4096'],
            'template_name' => ['required_if:type,template', 'nullable', 'string'],
            'template_language' => ['required_if:type,template', 'nullable', 'string'],
            'template_components' => ['nullable', 'array'],
        ]);

        $channel = $conversation->channel;
        if (! $channel || ! $channel->isConnected()) {
            return response()->json([
                'error' => [
                    'code' => 'channel_not_connected',
                    'message' => 'El canal no está conectado.',
                ],
            ], 422);
        }

        // Reglas WA Cloud (server-side, no confiar en el cliente):
        //  - texto libre solo dentro de la ventana 24h del último inbound
        //  - plantilla siempre permitida (Meta valida si está APPROVED)
        if ($data['type'] === 'text') {
            $error = $this->enforce24hWindow($conversation);
            if ($error) {
                return response()->json($error, 422);
            }
        }

        $payload = [
            'workspace_id' => $conversation->workspace_id,
            'conversation_id' => $conversation->id,
            'channel_id' => $channel->id,
            'contact_id' => $conversation->contact_id,
            'direction' => MessageDirection::Outbound->value,
            'status' => Message::STATUS_QUEUED,
            'type' => $data['type'],
            'body' => $data['body'] ?? null,
            'template_name' => $data['template_name'] ?? null,
            'template_payload' => $data['type'] === 'template' ? [
                'language' => $data['template_language'] ?? 'es',
                'components' => $data['template_components'] ?? [],
            ] : null,
        ];

        $message = Message::create($payload);

        SendOutboundMessage::dispatch($message->id)
            ->onQueue(config('sinapsa.queues.outbound'));

        $conversation->forceFill([
            'last_message_at' => now(),
            'unread_count' => 0,
        ])->save();

        return response()->json([
            'message' => new MessageResource($message),
        ], 202);
    }

    private function enforce24hWindow(Conversation $conversation): ?array
    {
        $hours = (int) config('sinapsa.whatsapp.customer_service_window_hours', 24);
        $lastInboundAt = $conversation->lastInboundAt();

        if (! $lastInboundAt || $lastInboundAt->lessThan(Carbon::now()->subHours($hours))) {
            return [
                'error' => [
                    'code' => 'outside_24h_window',
                    'message' => 'Fuera de la ventana de 24h. Envía una plantilla aprobada en su lugar.',
                    'param' => 'type',
                ],
            ];
        }

        return null;
    }
}
