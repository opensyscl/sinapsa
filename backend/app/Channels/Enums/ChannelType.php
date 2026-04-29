<?php

namespace App\Channels\Enums;

enum ChannelType: string
{
    case WhatsApp = 'whatsapp';
    case Instagram = 'instagram';
    case Messenger = 'messenger';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp Cloud',
            self::Instagram => 'Instagram DM',
            self::Messenger => 'Facebook Messenger',
        };
    }
}
