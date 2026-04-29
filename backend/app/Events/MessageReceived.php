<?php

namespace App\Events;

use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se dispara cuando llega un mensaje INBOUND ya persistido.
 * Lo emite el job `ProcessIncomingMetaWebhook` después de guardar.
 *
 * El frontend lo escucha en `private-workspace.{id}.inbox`.
 *
 * Implementa `ShouldBroadcastNow` (no `ShouldBroadcast`) porque queremos
 * que llegue al WebSocket lo antes posible — no encolar el broadcast
 * dentro de otra cola.
 */
class MessageReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("workspace.{$this->message->workspace_id}.inbox"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageReceived';
    }

    public function broadcastWith(): array
    {
        $conversation = $this->message->conversation()
            ->with(['channel', 'contact', 'assignedTo', 'latestMessage'])
            ->first();

        return [
            'message' => (new MessageResource($this->message))->toArray(request()),
            'conversation' => $conversation
                ? (new ConversationResource($conversation))->toArray(request())
                : null,
        ];
    }
}
