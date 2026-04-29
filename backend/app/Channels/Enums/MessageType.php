<?php

namespace App\Channels\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';
    case Document = 'document';
    case Template = 'template';
    case Interactive = 'interactive';
    case Reaction = 'reaction';
    case Location = 'location';
    case Sticker = 'sticker';
    case Unknown = 'unknown';
}
