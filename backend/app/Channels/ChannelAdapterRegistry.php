<?php

namespace App\Channels;

use App\Channels\Contracts\ChannelAdapter;
use App\Channels\Enums\ChannelType;
use App\Channels\Instagram\InstagramAdapter;
use App\Channels\Messenger\MessengerAdapter;
use App\Channels\WhatsAppCloud\WhatsAppCloudAdapter;
use InvalidArgumentException;

/**
 * Registro de adapters por tipo de canal. Bind en service container.
 *
 * Para añadir un canal nuevo (Telegram, etc.):
 *  1. Crear `App\Channels\Telegram\TelegramAdapter` que implementa `ChannelAdapter`.
 *  2. Añadirlo aquí.
 *  3. Cero cambios en jobs / controllers.
 */
class ChannelAdapterRegistry
{
    /** @var array<string, class-string<ChannelAdapter>> */
    protected array $map;

    public function __construct()
    {
        $this->map = [
            ChannelType::WhatsApp->value => WhatsAppCloudAdapter::class,
            ChannelType::Instagram->value => InstagramAdapter::class,
            ChannelType::Messenger->value => MessengerAdapter::class,
        ];
    }

    public function for(ChannelType|string $type): ChannelAdapter
    {
        $key = $type instanceof ChannelType ? $type->value : $type;

        if (! isset($this->map[$key])) {
            throw new InvalidArgumentException("No channel adapter registered for type [{$key}].");
        }

        return app($this->map[$key]);
    }

    public function supports(string $type): bool
    {
        return isset($this->map[$type]);
    }

    /** @return array<string> */
    public function supportedTypes(): array
    {
        return array_keys($this->map);
    }
}
