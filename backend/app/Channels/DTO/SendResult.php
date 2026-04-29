<?php

namespace App\Channels\DTO;

use Spatie\LaravelData\Data;

/**
 * Resultado de enviar un mensaje a Meta.
 * - success=true → guarda external_id en Message
 * - success=false → marca Message.failed con error_code/error_message
 */
class SendResult extends Data
{
    public function __construct(
        public bool $success,
        public ?string $external_id = null,
        public ?string $error_code = null,
        public ?string $error_message = null,
        public ?array $raw_response = null,
    ) {}
}
