<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WaTemplate */
class WaTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel_id' => $this->channel_id,
            'name' => $this->name,
            'language' => $this->language,
            'category' => $this->category,
            'status' => $this->status,
            'components' => $this->components,
            'meta_template_id' => $this->meta_template_id,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'rejected_reason' => $this->rejected_reason,
        ];
    }
}
