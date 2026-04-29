<?php

namespace App\Channels\Messenger;

use App\Channels\Contracts\ChannelAdapter;
use App\Channels\DTO\InboundParseResult;
use App\Channels\DTO\NormalizedMessage;
use App\Channels\DTO\SendResult;
use App\Channels\Enums\ChannelType;
use App\Channels\Support\MetaGraphClient;
use App\Channels\Support\MetaSignatureVerifier;
use App\Models\Channel;
use Illuminate\Support\Facades\Log;

class MessengerAdapter implements ChannelAdapter
{
    public function __construct(
        protected MessengerInboundParser $parser,
        protected MessengerOutboundBuilder $builder,
        protected MetaGraphClient $graph,
        protected MetaSignatureVerifier $signatureVerifier,
    ) {}

    public static function type(): ChannelType
    {
        return ChannelType::Messenger;
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
        $first = $payload['entry'][0]['id'] ?? null;

        return $first ? (string) $first : null;
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
                error_message: 'Channel sin access_token configurado.',
            );
        }

        $body = $this->builder->build($message);
        $response = $this->graph->withAccessToken($token)
            ->post("/{$channel->external_id}/messages", $body);

        Log::info('meta.graph.send.messenger', [
            'channel_id' => $channel->id,
            'status' => $response->status(),
            'request_payload' => $body,
            'response_body' => $response->json(),
        ]);

        if ($response->successful()) {
            $mid = data_get($response->json(), 'message_id');

            return new SendResult(
                success: true,
                external_id: $mid,
                raw_response: $response->json(),
            );
        }

        $err = $response->json();
        $errCode = (string) data_get($err, 'error.code', $response->status());

        return new SendResult(
            success: false,
            error_code: $errCode,
            error_message: (string) data_get($err, 'error.message', 'Unknown Messenger error'),
            raw_response: $err,
        );
    }
}
