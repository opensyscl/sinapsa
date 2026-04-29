<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Status update de un mensaje saliente: sent → delivered → read, o failed.
 * Lo emite el procesamiento de status webhooks de Meta.
 */
class MessageStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("workspace.{$this->message->workspace_id}.inbox"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => (new MessageResource($this->message))->toArray(request()),
        ];
    }
}
