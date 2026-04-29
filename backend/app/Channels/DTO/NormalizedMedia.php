<?php

namespace App\Channels\DTO;

use Spatie\LaravelData\Data;

class NormalizedMedia extends Data
{
    public function __construct(
        public string $external_id,        // Meta media ID (para descargar luego)
        public ?string $mime_type = null,
        public ?string $caption = null,
        public ?string $sha256 = null,
        public ?string $url = null,         // Si ya tenemos URL pública (lo más raro inbound)
        public ?string $filename = null,
    ) {}
}
