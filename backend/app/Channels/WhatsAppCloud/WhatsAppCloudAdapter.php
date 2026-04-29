<?php

namespace App\Channels\WhatsAppCloud;

use App\Channels\Contracts\ChannelAdapter;
use App\Channels\DTO\InboundParseResult;
use App\Channels\DTO\NormalizedMessage;
use App\Channels\DTO\SendResult;
use App\Channels\Enums\ChannelType;
use App\Channels\Support\MetaGraphClient;
use App\Channels\Support\MetaSignatureVerifier;
use App\Models\Channel;

class WhatsAppCloudAdapter implements ChannelAdapter
{
    public function __construct(
        protected WhatsAppCloudInboundParser $parser,
        protected WhatsAppCloudOutboundBuilder $builder,
        protected MetaGraphClient $graph,
        protected MetaSignatureVerifier $signatureVerifier,
    ) {}

    public static function type(): ChannelType
    {
        return ChannelType::WhatsApp;
    }

    public function verifySignature(string $rawBody, ?string $signatureHeader): bool
    {
        return $this->signatureVerifier->verify(
            signatureHeader: $signatureHeader,
            rawBody: $rawBody,
            appSecret: (string) config('sinapsa.meta.app_secret', ''),
        );
    }

    public function extractChannelExternalId(array $payload): ?string
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $field = $change['field'] ?? null;

                // Para `messages`, el id del canal es phone_number_id.
                if ($field === 'messages') {
                    $id = data_get($change, 'value.metadata.phone_number_id');
                    if ($id) {
                        return (string) $id;
                    }
                }

                // Para template updates Meta NO envía phone_number_id. Usamos
                // waba_id (entry.id) y resolvemos el canal por meta_business_id.
                if ($field === 'message_template_status_update' && isset($entry['id'])) {
                    return 'waba:' . $entry['id'];
                }
            }
        }

        return null;
    }

    public function parseInbound(array $payload): InboundParseResult
    {
        return $this->parser->parse($payload);
    }

    public function send(Channel $channel, NormalizedMessage $message): SendResult
    {
        $token = $channel->getAccessToken();
        if (! $token) {
            return new SendResult(
                success: false,
                error_code: 'channel_no_token',
                error_message: 'El canal no tiene access_token configurado.',
            );
        }

        $body = $this->builder->build($message);
        $response = $this->graph->sendWhatsAppMessage($token, $channel->external_id, $body);

        if ($response->successful()) {
            // Meta devuelve { messages: [{ id: "wamid.xxx" }], contacts: [...] }
            $wamid = data_get($response->json(), 'messages.0.id');

            return new SendResult(
                success: true,
                external_id: $wamid,
                raw_response: $response->json(),
            );
        }

        $err = $response->json();
        $errCode = (string) data_get($err, 'error.code', $response->status());
        $errMessage = (string) data_get($err, 'error.message', 'Unknown Meta error');
        $errSubcode = data_get($err, 'error.error_subcode');
        if ($errSubcode) {
            $errCode .= '/' . $errSubcode;
        }

        return new SendResult(
            success: false,
            error_code: $errCode,
            error_message: $errMessage,
            raw_response: $err,
        );
    }
}
