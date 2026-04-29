<?php

namespace App\Channels\WhatsAppCloud\Services;

use App\Channels\Support\MetaGraphClient;
use App\Exceptions\ApiException;
use App\Models\Channel;
use App\Models\WaTemplate;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Crea/elimina plantillas WhatsApp en Meta y mantiene el espejo local.
 *
 * Reference: https://developers.facebook.com/docs/whatsapp/business-management-api/message-templates
 *
 * Flujo de creación:
 *  1. POST /{waba_id}/message_templates con name, category, language, components.
 *  2. Meta responde con `id` y `status: PENDING`.
 *  3. Persistimos un WaTemplate local con status=PENDING.
 *  4. Mas tarde Meta dispara webhook `message_template_status_update` cuando
 *     aprueba/rechaza → actualizamos status local y emitimos `template.status_updated`.
 */
class WhatsAppTemplateCreateService
{
    public function __construct(
        protected MetaGraphClient $graph,
    ) {}

    /**
     * @param  array  $components Array de componentes Meta (BODY/HEADER/FOOTER/BUTTONS).
     *                            Mínimo un BODY.
     */
    public function create(
        Channel $channel,
        string $name,
        string $language,
        string $category,
        array $components,
    ): WaTemplate {
        if (! $channel->meta_business_id) {
            throw new RuntimeException("Channel {$channel->id} sin meta_business_id (waba_id).");
        }
        $token = $channel->getAccessToken();
        if (! $token) {
            throw new RuntimeException("Channel {$channel->id} sin access_token.");
        }

        // Verificación local previa (Meta también lo hace, pero le ahorramos el round-trip)
        if (WaTemplate::withoutGlobalScopes()
            ->where('channel_id', $channel->id)
            ->where('name', $name)
            ->where('language', $language)
            ->exists()
        ) {
            throw ApiException::invalidRequest(
                'template_already_exists',
                "Template [{$name}/{$language}] already exists on this channel.",
                'name',
            );
        }

        $payload = [
            'name' => $name,
            'category' => $category,
            'language' => $language,
            'components' => $components,
        ];

        $response = $this->graph->withAccessToken($token)
            ->post("/{$channel->meta_business_id}/message_templates", $payload);

        if (! $response->successful()) {
            $err = $response->json('error', []);
            Log::warning('wa_templates.create.failed', [
                'channel_id' => $channel->id,
                'name' => $name,
                'meta_error' => $err,
            ]);
            throw ApiException::invalidRequest(
                'meta_rejected_template',
                "Meta rejected template: " . ($err['message'] ?? 'Unknown error'),
                'components',
            );
        }

        $data = $response->json();

        $template = new WaTemplate([
            'channel_id' => $channel->id,
            'name' => $name,
            'language' => $language,
            'category' => $category,
            'status' => $data['status'] ?? WaTemplate::STATUS_PENDING,
            'components' => $components,
            'meta_template_id' => $data['id'] ?? null,
            'last_synced_at' => now(),
        ]);
        $template->workspace_id = $channel->workspace_id;
        $template->save();

        return $template;
    }

    /**
     * Elimina la plantilla en Meta y localmente.
     * Meta DELETE: /{waba_id}/message_templates?name=X&hsm_id=Y
     */
    public function delete(WaTemplate $template): bool
    {
        $channel = $template->channel;
        if (! $channel) {
            $template->delete();

            return true;
        }
        $token = $channel->getAccessToken();
        if (! $token) {
            $template->delete();

            return true;
        }

        // Meta DELETE requiere params como query string (no body) — construimos la URL.
        $params = ['name' => $template->name];
        if ($template->meta_template_id) {
            $params['hsm_id'] = $template->meta_template_id;
        }
        $url = "/{$channel->meta_business_id}/message_templates?" . http_build_query($params);

        $response = $this->graph->withAccessToken($token)->delete($url);

        // Si Meta devuelve 200 o el template ya no existía allí (404), borramos local.
        $okStatuses = [200, 404];
        if (! in_array($response->status(), $okStatuses, true)) {
            Log::warning('wa_templates.delete.meta_rejected', [
                'template_id' => $template->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return false;
        }

        $template->delete();

        return true;
    }
}
