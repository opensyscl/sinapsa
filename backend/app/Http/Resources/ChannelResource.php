<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Channel */
class ChannelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'type' => $this->type,
            'display_name' => $this->display_name,
            'external_id' => $this->external_id,
            'meta_business_id' => $this->meta_business_id,
            'status' => $this->status,
            'is_connected' => $this->isConnected(),
            'token_expires_at' => $this->token_expires_at?->toIso8601String(),
            'webhook_subscribed_at' => $this->webhook_subscribed_at?->toIso8601String(),
            'last_health_check_at' => $this->last_health_check_at?->toIso8601String(),
            'last_error_code' => $this->last_error_code,
            'last_error_message' => $this->last_error_message,
            'config' => $this->config,
            'templates_count' => $this->whenCounted('templates'),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
