"use client";

import { useState } from "react";
import { toast } from "sonner";
import { HugeiconsIcon } from "@hugeicons/react";
import {
  PlusSignIcon,
  Cancel01Icon,
  CodeIcon,
  Copy01Icon,
  ConnectIcon,
  Mailbox01Icon,
} from "@hugeicons/core-free-icons";
import dayjs from "dayjs";
import { Button } from "@/components/ui/Button";
import { Card, CardHeader } from "@/components/ui/Card";
import { Badge } from "@/components/ui/Badge";
import { cn } from "@/lib/utils";
import {
  useApiTokens,
  useRevokeApiToken,
  type ApiToken,
} from "@/lib/queries/api-tokens";
import {
  useWebhookEndpoints,
  useDeleteWebhook,
  type WebhookEndpoint,
} from "@/lib/queries/webhooks";
import { CreateTokenDialog } from "@/components/developers/CreateTokenDialog";
import { CreateWebhookDialog } from "@/components/developers/CreateWebhookDialog";
import { WebhookDeliveriesDialog } from "@/components/developers/WebhookDeliveriesDialog";

function TokenRow({ token }: { token: ApiToken }) {
  const revoke = useRevokeApiToken();
  return (
    <Card className="p-5">
      <div className="flex flex-wrap items-start gap-4">
        <div className="flex-1 min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-base font-semibold tracking-tight">{token.name}</h3>
            <Badge tone={token.mode === "live" ? "solid" : "outline"}>{token.mode}</Badge>
            {token.is_revoked && <Badge tone="destructive">revocado</Badge>}
          </div>
          <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <code className="font-mono">{token.prefix}…</code>
            <span>·</span>
            <span>
              Creado{" "}
              {token.created_by ? `por ${token.created_by.name} ` : ""}
              el {dayjs(token.created_at).format("DD/MM/YYYY")}
            </span>
            {token.last_used_at && (
              <>
                <span>·</span>
                <span>último uso {dayjs(token.last_used_at).format("DD/MM HH:mm")}</span>
              </>
            )}
          </div>
          <div className="mt-2 flex flex-wrap gap-1">
            {token.scopes.map((s) => (
              <code
                key={s}
                className="rounded-full border border-border bg-muted px-2 py-0.5 text-[11px]"
              >
                {s}
              </code>
            ))}
          </div>
        </div>
        {!token.is_revoked && (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              if (
                !confirm(
                  `¿Revocar "${token.name}"? Las requests con este token devolverán 401.`,
                )
              )
                return;
              revoke.mutate(token.id, {
                onSuccess: () => toast.success("Token revocado"),
              });
            }}
            disabled={revoke.isPending}
          >
            <HugeiconsIcon icon={Cancel01Icon} size={14} />
            Revocar
          </Button>
        )}
      </div>
    </Card>
  );
}

function WebhookRow({
  endpoint,
  onOpenDeliveries,
}: {
  endpoint: WebhookEndpoint;
  onOpenDeliveries: (e: WebhookEndpoint) => void;
}) {
  const del = useDeleteWebhook();
  return (
    <Card className="p-5">
      <div className="flex flex-wrap items-start gap-4">
        <div className="flex-1 min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <code className="truncate font-mono text-sm">{endpoint.url}</code>
            <Badge
              tone={
                endpoint.status === "active"
                  ? "positive"
                  : endpoint.status === "failing"
                    ? "destructive"
                    : "neutral"
              }
            >
              {endpoint.status}
            </Badge>
          </div>
          {endpoint.description && (
            <p className="mt-0.5 text-xs text-muted-foreground">{endpoint.description}</p>
          )}
          <div className="mt-2 flex flex-wrap gap-1">
            {endpoint.events.map((e) => (
              <code
                key={e}
                className="rounded-full border border-border bg-muted px-2 py-0.5 text-[11px]"
              >
                {e}
              </code>
            ))}
          </div>
          <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-0.5 text-[11px] text-muted-foreground">
            {endpoint.last_success_at && (
              <span>último OK {dayjs(endpoint.last_success_at).format("DD/MM HH:mm")}</span>
            )}
            {endpoint.consecutive_failures > 0 && (
              <span className="text-destructive">
                {endpoint.consecutive_failures} fallos consecutivos
              </span>
            )}
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" size="sm" onClick={() => onOpenDeliveries(endpoint)}>
            Ver deliveries
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              if (!confirm(`¿Eliminar webhook ${endpoint.url}?`)) return;
              del.mutate(endpoint.id, {
                onSuccess: () => toast.success("Webhook eliminado"),
              });
            }}
            disabled={del.isPending}
          >
            <HugeiconsIcon icon={Cancel01Icon} size={14} />
            Eliminar
          </Button>
        </div>
      </div>
    </Card>
  );
}

function CurlSnippet() {
  const [copied, setCopied] = useState(false);
  const snippet = `# Sustituye sk_live_xxx por tu token real
curl -X POST http://localhost:48000/api/v1/messages \\
  -H "Authorization: Bearer sk_live_xxx" \\
  -H "Idempotency-Key: $(uuidgen)" \\
  -H "Content-Type: application/json" \\
  -d '{
    "channel_id": 1,
    "to": { "phone": "+34666123456" },
    "type": "template",
    "template": { "name": "hello_world", "language": "en_US" }
  }'`;

  return (
    <Card className="p-5">
      <CardHeader className="flex flex-row items-center justify-between p-0 pb-3">
        <div>
          <h3 className="text-base font-semibold tracking-tight">Quickstart</h3>
          <p className="text-xs text-muted-foreground mt-0.5">
            Envía un mensaje desde la línea de comandos.
          </p>
        </div>
        <Button
          variant="outline"
          size="sm"
          onClick={async () => {
            await navigator.clipboard.writeText(snippet);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
          }}
        >
          <HugeiconsIcon icon={Copy01Icon} size={12} />
          {copied ? "Copiado" : "Copiar"}
        </Button>
      </CardHeader>
      <pre className="mt-3 overflow-x-auto rounded-2xl border border-border bg-muted/50 p-4 text-xs font-mono leading-relaxed">
        {snippet}
      </pre>
    </Card>
  );
}

export default function DevelopersPage() {
  const [tab, setTab] = useState<"tokens" | "webhooks">("tokens");
  const [openCreateToken, setOpenCreateToken] = useState(false);
  const [openCreateWebhook, setOpenCreateWebhook] = useState(false);
  const [deliveriesFor, setDeliveriesFor] = useState<WebhookEndpoint | null>(null);
  const tokens = useApiTokens();
  const webhooks = useWebhookEndpoints();

  return (
    <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-6 py-10">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-2xl font-semibold tracking-tight">Desarrolladores</h1>
          <p className="text-sm text-muted-foreground">
            Tokens API, webhooks salientes y documentación.
          </p>
        </div>
        {tab === "tokens" ? (
          <Button onClick={() => setOpenCreateToken(true)}>
            <HugeiconsIcon icon={PlusSignIcon} size={16} />
            Crear token
          </Button>
        ) : (
          <Button onClick={() => setOpenCreateWebhook(true)}>
            <HugeiconsIcon icon={PlusSignIcon} size={16} />
            Suscribir webhook
          </Button>
        )}
      </div>

      <div className="flex items-center gap-1 rounded-2xl border border-border bg-muted p-1 w-fit">
        <button
          type="button"
          onClick={() => setTab("tokens")}
          className={cn(
            "inline-flex items-center gap-2 rounded-xl px-4 py-1.5 text-sm font-medium transition-colors",
            tab === "tokens"
              ? "bg-background text-foreground shadow-sm"
              : "text-muted-foreground",
          )}
        >
          <HugeiconsIcon icon={CodeIcon} size={14} />
          Tokens API
        </button>
        <button
          type="button"
          onClick={() => setTab("webhooks")}
          className={cn(
            "inline-flex items-center gap-2 rounded-xl px-4 py-1.5 text-sm font-medium transition-colors",
            tab === "webhooks"
              ? "bg-background text-foreground shadow-sm"
              : "text-muted-foreground",
          )}
        >
          <HugeiconsIcon icon={ConnectIcon} size={14} />
          Webhooks
        </button>
      </div>

      {tab === "tokens" && (
        <div className="space-y-3">
          {tokens.isLoading && (
            <Card className="p-8 text-center text-sm text-muted-foreground">Cargando…</Card>
          )}
          {tokens.data?.data.length === 0 && (
            <Card className="p-10 text-center">
              <CardHeader className="space-y-3 text-center">
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-muted">
                  <HugeiconsIcon icon={CodeIcon} size={22} />
                </div>
                <h3 className="text-lg font-semibold tracking-tight">Aún sin tokens</h3>
                <p className="text-sm text-muted-foreground">
                  Crea tu primer token para empezar a llamar a la API.
                </p>
                <div className="flex justify-center pt-2">
                  <Button onClick={() => setOpenCreateToken(true)}>
                    <HugeiconsIcon icon={PlusSignIcon} size={16} />
                    Crear token
                  </Button>
                </div>
              </CardHeader>
            </Card>
          )}
          {tokens.data?.data.map((t) => <TokenRow key={t.id} token={t} />)}
          <CurlSnippet />
        </div>
      )}

      {tab === "webhooks" && (
        <div className="space-y-3">
          {webhooks.isLoading && (
            <Card className="p-8 text-center text-sm text-muted-foreground">Cargando…</Card>
          )}
          {webhooks.data?.data.length === 0 && (
            <Card className="p-10 text-center">
              <CardHeader className="space-y-3 text-center">
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-muted">
                  <HugeiconsIcon icon={Mailbox01Icon} size={22} />
                </div>
                <h3 className="text-lg font-semibold tracking-tight">Sin webhooks</h3>
                <p className="text-sm text-muted-foreground">
                  Suscribe una URL para recibir eventos en tiempo real.
                </p>
                <div className="flex justify-center pt-2">
                  <Button onClick={() => setOpenCreateWebhook(true)}>
                    <HugeiconsIcon icon={PlusSignIcon} size={16} />
                    Suscribir webhook
                  </Button>
                </div>
              </CardHeader>
            </Card>
          )}
          {webhooks.data?.data.map((e) => (
            <WebhookRow key={e.id} endpoint={e} onOpenDeliveries={setDeliveriesFor} />
          ))}
        </div>
      )}

      <CreateTokenDialog open={openCreateToken} onClose={() => setOpenCreateToken(false)} />
      <CreateWebhookDialog open={openCreateWebhook} onClose={() => setOpenCreateWebhook(false)} />
      <WebhookDeliveriesDialog
        open={!!deliveriesFor}
        onClose={() => setDeliveriesFor(null)}
        endpoint={deliveriesFor}
      />
    </main>
  );
}
