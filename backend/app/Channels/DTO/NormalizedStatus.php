<?php

namespace App\Channels\DTO;

use Spatie\LaravelData\Data;

/**
 * Update de estado de un mensaje saliente: sent / delivered / read / failed.
 * Meta los envía como webhook independiente del mensaje original.
 */
class NormalizedStatus extends Data
{
    public function __construct(
        public string $external_id,    // wamid del mensaje al que afecta
        public string $status,          // sent | delivered | read | failed
        public string $timestamp,       // ISO 8601
        public ?string $recipient_external_id = null,
        public ?string $error_code = null,
        public ?string $error_message = null,
        public ?array $raw = null,
    ) {}
}
