<?php

namespace App\Channels\DTO;

use Spatie\LaravelData\Data;

/**
 * Resultado de parsear un webhook entrante de Meta.
 *
 * Un solo webhook puede traer varios mensajes, varios statuses y/o
 * varios template updates. Cuando es del field `messages`, todos pertenecen al
 * mismo `channel_external_id` (phone_number_id en WA). Para template updates
 * Meta no envía phone_number_id; el waba_id va en el raw del DTO.
 */
class InboundParseResult extends Data
{
    public function __construct(
        public string $channel_external_id,
        /** @var NormalizedMessage[] */
        public array $messages = [],
        /** @var NormalizedStatus[] */
        public array $statuses = [],
        /** @var NormalizedTemplateUpdate[] */
        public array $template_updates = [],
    ) {}

    public function isEmpty(): bool
    {
        return empty($this->messages)
            && empty($this->statuses)
            && empty($this->template_updates);
    }
}
