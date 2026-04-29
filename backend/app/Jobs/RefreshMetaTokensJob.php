<?php

namespace App\Jobs;

use App\Channels\WhatsAppCloud\Services\MetaEmbeddedSignupService;
use App\Models\Channel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableTrait;
use Illuminate\Support\Facades\Log;

/**
 * Hace healthCheck a todos los canales conectados.
 *
 * - Si Meta devuelve 401/190 → marca channel.status = 'error'.
 * - Si token caduca en <10d → log de aviso (Fase 7 enviará email al admin del workspace).
 *
 * Diseñado para correr cada hora vía scheduler (Fase 4).
 */
class RefreshMetaTokensJob implements ShouldQueue
{
    use QueueableTrait, Queueable;

    public function __construct(public ?int $channelId = null) {}

    public function handle(MetaEmbeddedSignupService $service): void
    {
        $query = Channel::withoutGlobalScopes()->where('status', Channel::STATUS_CONNECTED);
        if ($this->channelId) {
            $query->where('id', $this->channelId);
        }

        $query->cursor()->each(function (Channel $channel) use ($service) {
            try {
                $ok = $service->healthCheck($channel);
                if (! $ok) {
                    Log::warning('channel.health_check.failed', [
                        'channel_id' => $channel->id,
                        'last_error' => $channel->last_error_message,
                    ]);

                    return;
                }

                if ($channel->token_expires_at && $channel->token_expires_at->lessThan(now()->addDays(10))) {
                    Log::warning('channel.token_expiring_soon', [
                        'channel_id' => $channel->id,
                        'expires_at' => $channel->token_expires_at->toIso8601String(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('channel.health_check.exception', [
                    'channel_id' => $channel->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
