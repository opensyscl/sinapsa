<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'is_super_admin' => $this->isSuperAdmin(),
            'workspace' => new WorkspaceResource($this->whenLoaded('workspace')),
        ];
    }
}
