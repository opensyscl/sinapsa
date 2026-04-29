<?php

namespace App\Channels\WhatsAppCloud\Services;

use App\Channels\Support\MetaGraphClient;
use App\Models\Channel;
use App\Models\WaTemplate;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sincroniza plantillas WA desde Meta hacia nuestra tabla `wa_templates`.
 *
 * Llamada desde:
 *  - MetaEmbeddedSignupService::connect() (sync inicial)
 *  - SyncWaTemplatesJob (re-sync periódico Fase 4)
 *  - Webhook `template.status_updated` (Fase 7)
 *  - UI manual "Sincronizar plantillas"
 */
class WhatsAppTemplateSyncService
{
    public function __construct(
        protected MetaGraphClient $graph,
    ) {}

    /**
     * @return int Número de plantillas sincronizadas.
     */
    public function sync(Channel $channel): int
    {
        if (! $channel->meta_business_id) {
            throw new RuntimeException("Channel {$channel->id} sin meta_business_id (waba_id).");
        }
        $token = $channel->getAccessToken();
        if (! $token) {
            throw new RuntimeException("Channel {$channel->id} sin access_token.");
        }

        $count = 0;
        $url = "/{$channel->meta_business_id}/message_templates";
        $params = ['limit' => 100];

        do {
            $resp = $this->graph->withAccessToken($token)->get($url, $params);
            if (! $resp->successful()) {
                Log::warning('wa_templates.sync.failed', [
                    'channel_id' => $channel->id,
                    'status' => $resp->status(),
                    'error' => $resp->json('error'),
                ]);
                break;
            }

            $data = $resp->json('data', []);
            foreach ($data as $template) {
                $this->upsertTemplate($channel, $template);
                $count++;
            }

            // Paginación cursor (Meta usa `paging.cursors.after`)
            $next = $resp->json('paging.cursors.after');
            if (! $next) {
                break;
            }
            $params['after'] = $next;
            $url = "/{$channel->meta_business_id}/message_templates";
        } while (true);

        return $count;
    }

    protected function upsertTemplate(Channel $channel, array $t): void
    {
        WaTemplate::withoutGlobalScopes()->updateOrCreate(
            [
                'channel_id' => $channel->id,
                'name' => (string) $t['name'],
                'language' => (string) ($t['language'] ?? 'es'),
            ],
            [
                'workspace_id' => $channel->workspace_id,
                'category' => $t['category'] ?? null,
                'status' => $t['status'] ?? WaTemplate::STATUS_PENDING,
                'components' => $t['components'] ?? null,
                'meta_template_id' => $t['id'] ?? null,
                'last_synced_at' => now(),
                'rejected_reason' => $t['rejected_reason'] ?? null,
            ],
        );
    }
}
