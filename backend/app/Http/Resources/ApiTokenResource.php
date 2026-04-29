<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ApiToken
 *
 * No expone `token_hash`. El plaintext SOLO se devuelve al crear (en el
 * controller), nunca aquí — al listar tokens ya creados, el plaintext es
 * irrecuperable por diseño.
 */
class ApiTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'prefix' => $this->prefix,
            'scopes' => $this->scopes,
            'mode' => $this->mode,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'last_used_ip' => $this->last_used_ip,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'is_revoked' => $this->isRevoked(),
            'created_at' => $this->created_at->toIso8601String(),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
                'email' => $this->createdBy?->email,
            ]),
        ];
    }
}
