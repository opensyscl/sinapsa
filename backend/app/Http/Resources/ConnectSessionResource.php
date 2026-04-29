<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ConnectSession */
class ConnectSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'jti' => $this->jti,
            'allowed_channel_types' => $this->allowed_channel_types,
            'display_label' => $this->display_label,
            'return_url' => $this->return_url,
            'client_metadata' => $this->client_metadata,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'completed_channel_id' => $this->completed_channel_id,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
