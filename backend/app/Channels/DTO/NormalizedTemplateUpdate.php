<?php

namespace App\Channels\DTO;

use Spatie\LaravelData\Data;

/**
 * Update de status de una plantilla notificado por Meta.
 *
 * Cuando Meta aprueba/rechaza/desactiva una plantilla envía un webhook con
 * `field: "message_template_status_update"`. Esto cubre los eventos:
 *  - APPROVED: tras la review.
 *  - REJECTED: con `reason` explicando.
 *  - PENDING / IN_APPEAL.
 *  - DISABLED / PAUSED: por incumplimiento de calidad.
 */
class NormalizedTemplateUpdate extends Data
{
    public function __construct(
        public string $template_name,
        public string $language,
        public string $status,           // APPROVED | REJECTED | PENDING | DISABLED | PAUSED | ...
        public ?string $meta_template_id = null,
        public ?string $reason = null,    // Solo cuando status=REJECTED
        public ?array $raw = null,
    ) {}
}
