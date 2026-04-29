<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\WebhookEndpoint
 *
 * NUNCA expone `secret_encrypted`. El secret plain se devuelve solo en la
 * respuesta del POST de creación, una vez.
 */
class WebhookEndpointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'url' => $this->url,
            'description' => $this->description,
            'events' => $this->events,
            'status' => $this->status,
            'last_success_at' => $this->last_success_at?->toIso8601String(),
            'last_failure_at' => $this->last_failure_at?->toIso8601String(),
            'consecutive_failures' => $this->consecutive_failures,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
