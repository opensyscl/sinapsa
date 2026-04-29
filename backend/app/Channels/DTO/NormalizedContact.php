<?php

namespace App\Channels\DTO;

use Spatie\LaravelData\Data;

/**
 * Identidad normalizada del contacto, independiente del canal.
 *
 * `external_id` es el ID del lado de Meta:
 *  - WhatsApp: wa_id (= phone E.164 sin "+" típicamente)
 *  - Instagram: ig_user_id (PSID o IGSID según subtipo)
 *  - Messenger: PSID
 *
 * `phone` solo se rellena cuando el canal lo proporciona (WhatsApp).
 */
class NormalizedContact extends Data
{
    public function __construct(
        public string $external_id,
        public ?string $name = null,
        public ?string $phone = null,
        public ?string $avatar_url = null,
    ) {}
}
