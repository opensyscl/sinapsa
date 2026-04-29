<?php

namespace App\Http\Controllers\Webhooks;

use App\Channels\ChannelAdapterRegistry;
use App\Channels\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingMetaWebhook;
use App\Models\Channel;
use App\Models\WebhookInboundLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint Meta webhook polimórfico — sirve los 3 canales:
 *   GET/POST /webhooks/meta/whatsapp
 *   GET/POST /webhooks/meta/instagram
 *   GET/POST /webhooks/meta/messenger
 *
 * El channelType viene como path param de la route. El controller usa el
 * adapter registrado en `ChannelAdapterRegistry` para verificar firma + parsear
 * + extraer el id del canal. Cero hardcoded por canal en este archivo.
 *
 * Reglas:
 *  - Verificar firma SIEMPRE (X-Hub-Signature-256, mismo APP_SECRET para los 3).
 *  - Persistir el payload en `webhook_inbound_log` ANTES de procesar.
 *  - Responder 200 INMEDIATO (Meta tiene timeout 5s y reintenta agresivo).
 *  - Procesar en cola — nunca en el request.
 */
class MetaWebhookController extends Controller
{
    public function __construct(
        protected ChannelAdapterRegistry $adapters,
    ) {}

    /**
     * GET handshake. Meta llama esto al suscribir o re-suscribir el endpoint.
     * Los 3 canales comparten el mismo verify token (config('sinapsa.meta.webhook_verify_token')).
     */
    public function verify(Request $request, string $channelType): Response
    {
        $this->ensureSupported($channelType);

        $mode = $request->query('hub_mode');
        $challenge = $request->query('hub_challenge');
        $token = $request->query('hub_verify_token');

        $expected = config('sinapsa.meta.webhook_verify_token');

        if ($mode === 'subscribe' && $token === $expected) {
            return response((string) $challenge, 200);
        }

        Log::warning('meta.webhook.verify_failed', [
            'channel_type' => $channelType,
            'mode' => $mode,
            'token_match' => $token === $expected,
        ]);

        return response('Forbidden', 403);
    }

    public function receive(Request $request, string $channelType): Response
    {
        $this->ensureSupported($channelType);

        $rawBody = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        $adapter = $this->adapters->for($channelType);
        $signatureValid = $adapter->verifySignature($rawBody, $signature);

        if (! $signatureValid) {
            Log::warning('meta.webhook.signature_invalid', [
                'channel_type' => $channelType,
                'has_header' => (bool) $signature,
                'body_size' => strlen($rawBody),
            ]);

            return response('Invalid signature', 401);
        }

        $payload = $request->json()->all();
        $dedupeKey = hash('sha256', $rawBody);

        if (WebhookInboundLog::where('dedupe_key', $dedupeKey)->exists()) {
            return response('Duplicate ignored', 200);
        }

        // Resolver canal. Casos especiales:
        //  - WhatsApp: id = phone_number_id (channels.external_id) o `waba:<waba_id>` para template updates → meta_business_id.
        //  - Instagram: id = ig_user_id (channels.external_id).
        //  - Messenger: id = page_id (channels.external_id).
        $channelExternalId = $adapter->extractChannelExternalId($payload);
        $channel = $this->resolveChannel($channelType, $channelExternalId);

        $log = WebhookInboundLog::create([
            'source' => $channelType,
            'dedupe_key' => $dedupeKey,
            'workspace_id' => $channel?->workspace_id,
            'channel_id' => $channel?->id,
            'signature_valid' => true,
            'payload' => $payload,
            'headers' => $this->safeHeaders($request),
            'created_at' => now(),
        ]);

        if ($channel) {
            ProcessIncomingMetaWebhook::dispatch(
                logId: $log->id,
                channelId: $channel->id,
                channelType: $channelType,
            )->onQueue(config('sinapsa.queues.inbound'));
        } else {
            Log::info('meta.webhook.unknown_channel', [
                'channel_type' => $channelType,
                'channel_external_id' => $channelExternalId,
                'log_id' => $log->id,
            ]);
        }

        return response('OK', 200);
    }

    protected function resolveChannel(string $channelType, ?string $channelExternalId): ?Channel
    {
        if (! $channelExternalId) {
            return null;
        }
        $query = Channel::withoutGlobalScopes()->where('type', $channelType);

        if (str_starts_with($channelExternalId, 'waba:')) {
            return $query->where('meta_business_id', substr($channelExternalId, 5))->first();
        }

        return $query->where('external_id', $channelExternalId)->first();
    }

    protected function ensureSupported(string $channelType): void
    {
        if (! $this->adapters->supports($channelType)) {
            abort(404, "Channel type [{$channelType}] not supported.");
        }
    }

    protected function safeHeaders(Request $request): array
    {
        $blocklist = ['cookie', 'authorization', 'set-cookie'];
        $out = [];
        foreach ($request->headers->all() as $name => $values) {
            if (in_array(strtolower($name), $blocklist, true)) {
                continue;
            }
            $out[$name] = is_array($values) && count($values) === 1 ? $values[0] : $values;
        }

        return $out;
    }
}
