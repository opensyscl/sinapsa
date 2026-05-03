<?php

namespace App\Console\Commands;

use App\Channels\Enums\ChannelType;
use App\Channels\Support\MetaGraphClient;
use App\Models\Channel;
use App\Models\Workspace;
use Illuminate\Console\Command;

class AddInstagramChannel extends Command
{
    protected $signature = 'channels:add-instagram
                            {workspace_slug : Slug del workspace destino}
                            {--page-id= : Page ID de la Página de Facebook vinculada a la cuenta IG}
                            {--ig-user-id= : Instagram User ID (también llamado ig_business_account_id)}
                            {--token= : Page Access Token (largo, 60d) — Graph API Explorer o Embedded Signup}
                            {--display-name= : Nombre legible del canal en Sinapsa}
                            {--skip-subscribe : No llamar a /{page_id}/subscribed_apps (útil si ya está suscrita)}
                            {--force : Saltar validación del token contra Graph API}';

    protected $description = 'Registra un canal Instagram en un workspace, valida token y suscribe la página a los webhooks de la app.';

    public function handle(MetaGraphClient $graph): int
    {
        $slug = (string) $this->argument('workspace_slug');
        $workspace = Workspace::where('slug', $slug)->first();
        if (! $workspace) {
            $this->error("Workspace [{$slug}] no encontrado.");

            return self::FAILURE;
        }

        $pageId = trim((string) ($this->option('page-id') ?: $this->ask('Page ID (Facebook)')));
        $igUserId = trim((string) ($this->option('ig-user-id') ?: $this->ask('Instagram User ID (ig_business_account_id)')));
        $token = trim((string) ($this->option('token') ?: $this->secret('Page Access Token')));

        if ($pageId === '' || $igUserId === '' || $token === '') {
            $this->error('page-id, ig-user-id y token son obligatorios.');

            return self::FAILURE;
        }

        $displayName = (string) ($this->option('display-name') ?: '');
        $igUsername = null;

        // 1. Validar token + obtener metadata real de la página y la IG vinculada
        if (! $this->option('force')) {
            $pageInfo = $graph->withAccessToken($token)
                ->get("/{$pageId}", ['fields' => 'id,name,instagram_business_account{id,username,name}']);

            if (! $pageInfo->successful()) {
                $err = $pageInfo->json('error.message') ?? $pageInfo->body();
                $this->error("✗ Token inválido o sin permisos sobre la página {$pageId}: {$err}");

                return self::FAILURE;
            }

            $data = $pageInfo->json();
            $pageName = $data['name'] ?? '(sin nombre)';
            $igLinked = $data['instagram_business_account']['id'] ?? null;
            $igUsername = $data['instagram_business_account']['username'] ?? null;

            $this->line("  Página FB:    <fg=cyan>{$pageName}</> (id={$pageId})");
            $this->line('  IG vinculada: <fg=cyan>'.($igUsername ? '@'.$igUsername : '(no vinculada)').'</> (id='.($igLinked ?? 'null').')');

            if ($igLinked === null) {
                $this->error('✗ Esa página NO tiene una cuenta Instagram Business vinculada. Vincúlala desde la app móvil de Instagram → Centro de cuentas.');

                return self::FAILURE;
            }

            if ((string) $igLinked !== $igUserId) {
                $this->warn("⚠ El --ig-user-id que pasaste ({$igUserId}) no coincide con el que devuelve Meta ({$igLinked}). Voy a usar el de Meta.");
                $igUserId = (string) $igLinked;
            }

            if ($displayName === '') {
                $displayName = $igUsername ? '@'.$igUsername : "Instagram {$igUserId}";
            }
        } elseif ($displayName === '') {
            $displayName = "Instagram {$igUserId}";
        }

        // 2. Suscribir la página a los webhooks de TU app Meta
        $subscribed = false;
        if (! $this->option('skip-subscribe')) {
            $sub = $graph->withAccessToken($token)
                ->post("/{$pageId}/subscribed_apps", [
                    'subscribed_fields' => 'messages,messaging_postbacks,messaging_seen',
                ]);

            if ($sub->successful() && ($sub->json('success') === true)) {
                $subscribed = true;
                $this->line('  Suscripción webhook: <fg=green>OK</>');
            } else {
                $this->warn('  ⚠ No se pudo suscribir la página a webhooks: '.($sub->json('error.message') ?? $sub->body()));
                $this->warn('    Puedes seguir sin esto si ya la suscribiste manualmente desde Meta Console.');
            }
        }

        // 3. Persistir el canal
        $channel = Channel::withoutGlobalScopes()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'type' => ChannelType::Instagram->value,
                'external_id' => $igUserId,
            ],
            [
                'display_name' => $displayName,
                'meta_business_id' => $workspace->meta_business_id,
                'status' => Channel::STATUS_CONNECTED,
                'webhook_subscribed_at' => $subscribed ? now() : null,
                'config' => [
                    'page_id' => $pageId,
                    'created_via' => 'channels:add-instagram',
                ],
            ],
        );

        $channel->setAccessToken($token);
        $channel->save();

        $this->newLine();
        $this->info("✓ Canal Instagram listo: id={$channel->id} workspace={$workspace->slug} ig_user_id={$igUserId}");
        $this->line('  Webhook callback: <fg=cyan>'.config('sinapsa.meta.public_webhook_url').'/instagram</>');
        $this->line('  Manda un DM a '.($igUsername ? '@'.$igUsername : 'esa cuenta IG').' para verificar inbound.');

        return self::SUCCESS;
    }
}
