<?php

namespace App\Jobs;

use App\Channels\WhatsAppCloud\Services\WhatsAppTemplateSyncService;
use App\Models\Channel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableTrait;
use Illuminate\Support\Facades\Log;

class SyncWaTemplatesJob implements ShouldQueue
{
    use QueueableTrait, Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public int $channelId) {}

    public function handle(WhatsAppTemplateSyncService $service): void
    {
        $channel = Channel::withoutGlobalScopes()->find($this->channelId);
        if (! $channel || ! $channel->isConnected()) {
            return;
        }

        $count = $service->sync($channel);
        Log::info('wa_templates.sync.completed', [
            'channel_id' => $channel->id,
            'count' => $count,
        ]);
    }
}
