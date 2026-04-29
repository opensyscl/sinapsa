<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin \App\Models\Conversation */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'channel_id' => $this->channel_id,
            'contact_id' => $this->contact_id,
            'external_thread_id' => $this->external_thread_id,
            'status' => $this->status,
            'assigned_to_user_id' => $this->assigned_to_user_id,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'unread_count' => (int) $this->unread_count,
            'metadata' => $this->metadata,

            'channel' => $this->whenLoaded('channel', fn () => [
                'id' => $this->channel->id,
                'type' => $this->channel->type,
                'display_name' => $this->channel->display_name,
                'status' => $this->channel->status,
            ]),
            // Las relaciones que pueden ser null incluso cuando se eager-loadean
            // (e.g. assigned_to_user_id null) tienen que comprobarse explícitamente:
            // `new UserMiniResource(null)` revienta al acceder $this->id en toArray.
            'contact' => $this->whenLoaded('contact', fn () =>
                $this->contact ? new ContactMiniResource($this->contact) : null
            ),
            'assigned_to' => $this->whenLoaded('assignedTo', fn () =>
                $this->assignedTo ? new UserMiniResource($this->assignedTo) : null
            ),

            // Resumen del último mensaje, para listar en sidebar sin pedir mensajes
            'last_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? [
                'id' => $this->latestMessage->id,
                'direction' => $this->latestMessage->direction,
                'type' => $this->latestMessage->type,
                'status' => $this->latestMessage->status,
                'preview' => $this->buildPreview($this->latestMessage),
                'created_at' => $this->latestMessage->created_at?->toIso8601String(),
            ] : null),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Resumen del último mensaje para mostrar en la lista lateral. Truncamos
     * texto a ~80 chars y damos un placeholder por tipo cuando no hay body.
     */
    private function buildPreview($msg): string
    {
        if ($msg->body) {
            return Str::limit($msg->body, 80, '…');
        }

        return match ($msg->type) {
            'image' => '📷 Imagen',
            'audio' => '🎙️ Audio',
            'video' => '🎬 Vídeo',
            'document' => '📎 Documento',
            'location' => '📍 Ubicación',
            'sticker' => '🎴 Sticker',
            'template' => "Plantilla · {$msg->template_name}",
            default => '—',
        };
    }
}
