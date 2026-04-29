<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Message */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'channel_id' => $this->channel_id,
            'contact_id' => $this->contact_id,
            'direction' => $this->direction,
            'status' => $this->status,
            'type' => $this->type,
            'external_id' => $this->external_id,
            'body' => $this->body,
            'media_url' => $this->media_url,
            'media_mime' => $this->media_mime,
            'template_name' => $this->template_name,
            'template_payload' => $this->template_payload,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
