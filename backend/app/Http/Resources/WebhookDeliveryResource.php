<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WebhookDelivery */
class WebhookDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'endpoint_id' => $this->endpoint_id,
            'event_id' => $this->event_id,
            'event_type' => $this->event_type,
            'status' => $this->status,
            'attempt' => $this->attempt,
            'response_status' => $this->response_status,
            'response_body_preview' => $this->response_body
                ? mb_substr((string) $this->response_body, 0, 500)
                : null,
            'error_message' => $this->error_message,
            'next_attempt_at' => $this->next_attempt_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // Solo cuando se piden detalles concretos
            'payload' => $this->when($request->boolean('include_payload'), fn () => $this->payload),
            'response_headers' => $this->when($request->boolean('include_response'), fn () => $this->response_headers),
            'response_body' => $this->when($request->boolean('include_response'), fn () => $this->response_body),
        ];
    }
}
