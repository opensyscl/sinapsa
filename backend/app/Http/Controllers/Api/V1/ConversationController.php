<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactResource;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    /**
     * Listado de conversaciones para la bandeja.
     * Filtros: status (open|closed|...), channel_id, assigned_to_user_id, q (búsqueda).
     */
    public function index(Request $request): JsonResponse
    {
        $q = Conversation::query()
            ->with(['channel', 'contact', 'assignedTo', 'latestMessage'])
            ->orderByDesc('last_message_at');

        if ($status = $request->string('status')->toString()) {
            $q->where('status', $status);
        }
        if ($channelId = $request->integer('channel_id')) {
            $q->where('channel_id', $channelId);
        }
        if ($assigned = $request->integer('assigned_to_user_id')) {
            $q->where('assigned_to_user_id', $assigned);
        }
        if ($search = $request->string('q')->toString()) {
            // Búsqueda por nombre/teléfono del contacto
            $q->whereHas('contact', function ($query) use ($search) {
                $query->where('name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        $conversations = $q->paginate($request->integer('per_page', 30));

        return response()->json([
            'data' => ConversationResource::collection($conversations),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $conversation->load(['channel', 'contact', 'assignedTo']);

        return response()->json([
            'conversation' => new ConversationResource($conversation),
            'contact' => new ContactResource($conversation->contact),
        ]);
    }

    /**
     * Mensajes de una conversación. Cursor-based: `?cursor=...&limit=50`.
     * Devuelve en orden DESC y el cliente los pinta inverso (último abajo).
     */
    public function messages(Conversation $conversation, Request $request): JsonResponse
    {
        $limit = min((int) $request->integer('limit', 50), 200);

        $messages = $conversation->messages()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($limit);

        return response()->json([
            'data' => MessageResource::collection($messages),
            'meta' => [
                'next_cursor' => $messages->nextCursor()?->encode(),
                'prev_cursor' => $messages->previousCursor()?->encode(),
            ],
        ]);
    }

    public function update(Conversation $conversation, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:open,pending,closed,snoozed'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'snoozed_until' => ['nullable', 'date'],
        ]);

        $conversation->fill(array_filter($data, fn ($v) => $v !== null))->save();
        $conversation->load(['channel', 'contact', 'assignedTo', 'latestMessage']);

        return response()->json([
            'conversation' => new ConversationResource($conversation),
        ]);
    }

    public function markRead(Conversation $conversation): JsonResponse
    {
        $conversation->forceFill(['unread_count' => 0])->save();

        return response()->json([
            'ok' => true,
            'conversation' => new ConversationResource(
                $conversation->load(['channel', 'contact', 'assignedTo', 'latestMessage']),
            ),
        ]);
    }
}
