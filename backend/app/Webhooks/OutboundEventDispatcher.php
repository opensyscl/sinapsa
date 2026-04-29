<?php

namespace App\Webhooks;

use App\Jobs\DeliverWebhookJob;
use App\Models\Message;
use App\Models\WaTemplate;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\Workspace;

/**
 * Punto único de salida para eventos del workspace hacia el exterior.
 *
 *   $dispatcher->messageReceived($message);
 *   $dispatcher->messageStatus('message.delivered', $message);
 *   $dispatcher->templateStatusUpdated($template);
 *
 * Lo que hace:
 *  1) Construye el payload normalizado (EventPayloadBuilder).
 *  2) Busca todos los endpoints activos del workspace suscritos al evento.
 *  3) Por cada endpoint persiste un WebhookDelivery (status='pending')
 *     y dispatcheia DeliverWebhookJob a la cola `webhooks-out`.
 *
 * Importante: NO hace HTTP en plena petición. Outbox pattern — la persistencia
 * es la fuente de verdad y el job se ocupa del transporte con retries.
 */
class OutboundEventDispatcher
{
    public function __construct(
        protected EventPayloadBuilder $builder,
    ) {}

    public function messageReceived(Message $message): void
    {
        $this->emit(
            'message.received',
            $message->workspace_id,
            fn (Workspace $w) => $this->builder->forMessage('message.received', $message, $w),
        );
    }

    public function messageSent(Message $message): void
    {
        $this->emit(
            'message.sent',
            $message->workspace_id,
            fn (Workspace $w) => $this->builder->forMessage('message.sent', $message, $w),
        );
    }

    public function messageStatus(string $eventType, Message $message): void
    {
        $this->emit(
            $eventType,
            $message->workspace_id,
            fn (Workspace $w) => $this->builder->forMessage($eventType, $message, $w),
        );
    }

    public function templateStatusUpdated(WaTemplate $template): void
    {
        $this->emit(
            'template.status_updated',
            $template->workspace_id,
            fn (Workspace $w) => $this->builder->forTemplate('template.status_updated', $template, $w),
        );
    }

    /**
     * Helper genérico: localiza endpoints suscritos y crea deliveries.
     *
     * @param  callable(Workspace): array  $buildPayload  closure que construye el payload (lazy).
     */
    protected function emit(string $eventType, int $workspaceId, callable $buildPayload): void
    {
        $workspace = Workspace::find($workspaceId);
        if (! $workspace) {
            return;
        }

        $endpoints = WebhookEndpoint::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', [WebhookEndpoint::STATUS_ACTIVE, WebhookEndpoint::STATUS_FAILING])
            ->get()
            ->filter(fn (WebhookEndpoint $e) => $e->isSubscribedTo($eventType));

        if ($endpoints->isEmpty()) {
            return; // sin subscribers, nada que entregar
        }

        $payload = $buildPayload($workspace);

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::create([
                'workspace_id' => $workspace->id,
                'endpoint_id' => $endpoint->id,
                'event_id' => $payload['id'],
                'event_type' => $eventType,
                'payload' => $payload,
                'attempt' => 0,
                'status' => WebhookDelivery::STATUS_PENDING,
            ]);

            DeliverWebhookJob::dispatch($delivery->id)
                ->onQueue(config('sinapsa.queues.webhooks_out'));
        }
    }
}
