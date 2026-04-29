"use client";

import dayjs from "dayjs";
import { toast } from "sonner";
import { HugeiconsIcon } from "@hugeicons/react";
import { RefreshIcon } from "@hugeicons/core-free-icons";
import {
  Dialog,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/Dialog";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import {
  useReplayDelivery,
  useTestWebhook,
  useWebhookDeliveries,
  type WebhookDelivery,
  type WebhookEndpoint,
} from "@/lib/queries/webhooks";

function StatusBadge({ status }: { status: WebhookDelivery["status"] }) {
  const tone = status === "delivered"
    ? "positive"
    : status === "dead"
      ? "destructive"
      : status === "failing"
        ? "warning"
        : "neutral";
  return <Badge tone={tone}>{status}</Badge>;
}

export function WebhookDeliveriesDialog({
  open,
  onClose,
  endpoint,
}: {
  open: boolean;
  onClose: () => void;
  endpoint: WebhookEndpoint | null;
}) {
  const deliveries = useWebhookDeliveries(open && endpoint ? endpoint.id : undefined);
  const replay = useReplayDelivery();
  const test = useTestWebhook();

  if (!endpoint) return null;

  return (
    <Dialog open={open} onClose={onClose} className="max-w-3xl">
      <DialogHeader>
        <DialogTitle>Deliveries · {endpoint.url}</DialogTitle>
        <DialogDescription>
          Histórico de entregas. Polling cada 5s mientras esté abierto.
        </DialogDescription>
      </DialogHeader>

      <div className="mb-4 flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          onClick={() =>
            test.mutate(endpoint.id, {
              onSuccess: () => toast.success("Test event encolado"),
              onError: () => toast.error("Error al disparar test"),
            })
          }
          disabled={test.isPending}
        >
          Disparar test event
        </Button>
        <span className="text-xs text-muted-foreground">
          {deliveries.data?.length ?? 0} deliveries
        </span>
      </div>

      <div className="max-h-[60vh] space-y-2 overflow-y-auto">
        {deliveries.isLoading && (
          <p className="text-sm text-muted-foreground">Cargando…</p>
        )}
        {deliveries.data?.length === 0 && (
          <p className="text-sm text-muted-foreground">Sin deliveries todavía.</p>
        )}
        {deliveries.data?.map((d) => (
          <div
            key={d.id}
            className="rounded-2xl border border-border bg-background p-3 text-sm"
          >
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div className="flex items-center gap-2 min-w-0">
                <code className="font-mono text-xs">{d.event_type}</code>
                <StatusBadge status={d.status} />
                {d.response_status && (
                  <Badge tone={d.response_status < 300 ? "positive" : "destructive"}>
                    HTTP {d.response_status}
                  </Badge>
                )}
                {d.attempt > 0 && (
                  <span className="text-xs text-muted-foreground">
                    intento {d.attempt}/6
                  </span>
                )}
              </div>
              <Button
                variant="ghost"
                size="sm"
                onClick={() =>
                  replay.mutate(
                    { endpointId: endpoint.id, deliveryId: d.id },
                    { onSuccess: () => toast.success("Replay encolado") },
                  )
                }
                disabled={replay.isPending}
              >
                <HugeiconsIcon icon={RefreshIcon} size={12} />
                Replay
              </Button>
            </div>

            <div className="mt-1.5 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-muted-foreground">
              <span>
                <code className="font-mono">{d.event_id}</code>
              </span>
              <span>{dayjs(d.created_at).format("DD/MM HH:mm:ss")}</span>
              {d.next_attempt_at && d.status === "failing" && (
                <span className="text-warning">
                  reintenta {dayjs(d.next_attempt_at).format("HH:mm:ss")}
                </span>
              )}
            </div>

            {d.error_message && (
              <p className="mt-2 rounded-xl border border-destructive/20 bg-destructive/5 px-2.5 py-1.5 text-xs text-destructive">
                {d.error_message}
              </p>
            )}
            {d.response_body_preview && (
              <details className="mt-2">
                <summary className="cursor-pointer text-xs text-muted-foreground hover:text-foreground">
                  Response body ({d.response_body_preview.length} chars)
                </summary>
                <pre className="mt-1.5 overflow-x-auto rounded-xl border border-border bg-muted/40 p-2 text-[11px]">
                  {d.response_body_preview}
                </pre>
              </details>
            )}
          </div>
        ))}
      </div>

      <DialogFooter>
        <Button onClick={onClose}>Cerrar</Button>
      </DialogFooter>
    </Dialog>
  );
}
