<?php

namespace App\Integrations;

use App\Models\Integration;

/**
 * Tipos de integración que una Connect Session puede ofrecer, junto a los
 * channel types de Meta en `allowed_channel_types`.
 */
enum IntegrationType: string
{
    case GoogleCalendar = 'google_calendar';

    public function provider(): string
    {
        return match ($this) {
            self::GoogleCalendar => Integration::PROVIDER_GOOGLE,
        };
    }

    public function product(): string
    {
        return match ($this) {
            self::GoogleCalendar => Integration::PRODUCT_CALENDAR,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::GoogleCalendar => 'Google Calendar',
        };
    }
}
