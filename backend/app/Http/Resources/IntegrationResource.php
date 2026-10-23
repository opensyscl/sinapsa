<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Integration */
class IntegrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'provider' => $this->provider,
            'products' => $this->products,
            'external_id' => $this->external_id,
            'email' => $this->email,
            'display_name' => $this->display_name,
            'status' => $this->status,
            'is_connected' => $this->isConnected(),
            'granted_scopes' => $this->granted_scopes,
            'last_error_code' => $this->last_error_code,
            'last_error_message' => $this->last_error_message,
            'end_user_id' => $this->end_user_id,
            'client_metadata' => $this->client_metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
