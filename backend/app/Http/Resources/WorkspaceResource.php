<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Workspace */
class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'status' => $this->status,
            'logo_url' => $this->logo_url,
            'contact_email' => $this->contact_email,
            'retention_days' => $this->retention_days,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
