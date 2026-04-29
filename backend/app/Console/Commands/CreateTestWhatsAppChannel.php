<?php

namespace App\Console\Commands;

use App\Channels\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Workspace;
use Illuminate\Console\Command;

class CreateTestWhatsAppChannel extends Command
{
    protected $signature = 'channels:create-test-whatsapp
                            {workspace_slug : Slug del workspace}
                            {--phone-number-id=PHONE_NUMBER_ID_TEST}
                            {--display-name=WhatsApp de prueba}';

    protected $description = 'Crea un canal WhatsApp de prueba en un workspace (dev only)';

    public function handle(): int
    {
        $slug = (string) $this->argument('workspace_slug');
        $workspace = Workspace::where('slug', $slug)->first();

        if (! $workspace) {
            $this->error("Workspace [{$slug}] no encontrado.");

            return self::FAILURE;
        }

        $phoneNumberId = (string) $this->option('phone-number-id');

        $channel = Channel::withoutGlobalScopes()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'type' => ChannelType::WhatsApp->value,
                'external_id' => $phoneNumberId,
            ],
            [
                'display_name' => (string) $this->option('display-name'),
                'meta_business_id' => 'TEST_WABA_ID',
                'status' => Channel::STATUS_CONNECTED,
                'webhook_subscribed_at' => now(),
                'config' => [
                    'business_phone' => '+34900111222',
                    'created_via' => 'channels:create-test-whatsapp',
                ],
            ],
        );

        // Token "fake" cifrado para que send() pueda intentarlo (fallará Meta pero la lógica corre)
        $channel->setAccessToken('FAKE_DEV_TOKEN_NOT_VALID_AT_META');
        $channel->save();

        $this->info("✓ Canal creado: id={$channel->id} workspace={$workspace->slug} phone_number_id={$phoneNumberId}");

        return self::SUCCESS;
    }
}
