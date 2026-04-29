"use client";

import { useState } from "react";
import { toast } from "sonner";
import { HugeiconsIcon } from "@hugeicons/react";
import {
  WhatsappIcon,
  InstagramIcon,
  FacebookIcon,
  PlusSignIcon,
  RefreshIcon,
  Cancel01Icon,
  CheckmarkCircle01Icon,
  AlertCircleIcon,
  Clock01Icon,
} from "@hugeicons/core-free-icons";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { Card, CardHeader } from "@/components/ui/Card";
import {
  useChannels,
  useDisconnectChannel,
  useChannelHealthCheck,
  useSyncTemplates,
  type Channel,
} from "@/lib/queries/channels";
import { ConnectChannelDialog } from "@/components/channels/ConnectChannelDialog";
import dayjs from "dayjs";

const channelIcon = {
  whatsapp: WhatsappIcon,
  instagram: InstagramIcon,
  messenger: FacebookIcon,
} as const;

function StatusBadge({ status }: { status: Channel["status"] }) {
  if (status === "connected")
    return (
      <Badge tone="positive">
        <HugeiconsIcon icon={CheckmarkCircle01Icon} size={12} />
        Conectado
      </Badge>
    );
  if (status === "error")
    return (
      <Badge tone="destructive">
        <HugeiconsIcon icon={AlertCircleIcon} size={12} />
        Error
      </Badge>
    );
  if (status === "pending")
    return (
      <Badge tone="warning">
        <HugeiconsIcon icon={Clock01Icon} size={12} />
        Pendiente
      </Badge>
    );
  return <Badge tone="neutral">Desconectado</Badge>;
}

function ChannelRow({ channel }: { channel: Channel }) {
  const disconnect = useDisconnectChannel();
  const healthCheck = useChannelHealthCheck();
  const sync = useSyncTemplates();

  const Icon = channelIcon[channel.type] ?? WhatsappIcon;

  return (
    <Card className="p-5">
      <div className="flex flex-wrap items-start gap-4">
        <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-muted">
          <HugeiconsIcon icon={Icon} size={22} />
        </div>

        <div className="flex-1 min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-base font-semibold tracking-tight">{channel.display_name}</h3>
            <StatusBadge status={channel.status} />
            {channel.config?.connected_via === "manual" && (
              <Badge tone="outline">manual</Badge>
            )}
          </div>
          <p className="text-xs text-muted-foreground mt-1 truncate">
            {channel.type} · phone_number_id <code>{channel.external_id}</code>
            {channel.meta_business_id && (
              <>
                {" · "}WABA <code>{channel.meta_business_id}</code>
              </>
            )}
          </p>
          <p className="text-xs text-muted-foreground mt-0.5">
            {channel.token_expires_at
              ? `Token expira ${dayjs(channel.token_expires_at).format("DD/MM/YYYY")}`
              : "Sin token caducidad registrada"}
            {channel.templates_count !== undefined && (
              <> · {channel.templates_count} plantillas</>
            )}
          </p>
          {channel.status === "error" && channel.last_error_message && (
            <p className="mt-2 rounded-2xl border border-destructive/20 bg-destructive/5 px-3 py-2 text-xs text-destructive">
              {channel.last_error_code ? `[${channel.last_error_code}] ` : ""}
              {channel.last_error_message}
            </p>
          )}
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() =>
              healthCheck.mutate(channel.id, {
                onSuccess: ({ ok }) =>
                  toast[ok ? "success" : "error"](
                    ok ? "Canal saludable" : "Health check falló",
                  ),
                onError: () => toast.error("Health check falló"),
              })
            }
            disabled={healthCheck.isPending}
          >
            <HugeiconsIcon icon={RefreshIcon} size={14} />
            Test
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={() =>
              sync.mutate(channel.id, {
                onSuccess: (n) => toast.success(`${n} plantillas sincronizadas`),
                onError: (e) => {
                  const err = e as { response?: { data?: { error?: { message?: string } } } };
                  toast.error(err.response?.data?.error?.message ?? "Error sync plantillas");
                },
              })
            }
            disabled={sync.isPending || channel.status !== "connected"}
          >
            Sync plantillas
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              if (!confirm(`¿Desconectar "${channel.display_name}"?`)) return;
              disconnect.mutate(channel.id, {
                onSuccess: () => toast.success("Canal desconectado"),
              });
            }}
            disabled={disconnect.isPending}
          >
            <HugeiconsIcon icon={Cancel01Icon} size={14} />
            Desconectar
          </Button>
        </div>
      </div>
    </Card>
  );
}

export default function ChannelsPage() {
  const [open, setOpen] = useState(false);
  const channels = useChannels();

  return (
    <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-6 py-10">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-2xl font-semibold tracking-tight">Canales</h1>
          <p className="text-sm text-muted-foreground">
            Conecta WhatsApp, Instagram o Messenger para empezar a recibir y enviar mensajes.
          </p>
        </div>

        <Button onClick={() => setOpen(true)}>
          <HugeiconsIcon icon={PlusSignIcon} size={16} />
          Conectar canal
        </Button>
      </div>

      {channels.isLoading && (
        <Card className="p-8 text-center text-sm text-muted-foreground">Cargando…</Card>
      )}

      {channels.data?.length === 0 && (
        <Card className="p-10 text-center">
          <CardHeader className="space-y-3 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-muted">
              <HugeiconsIcon icon={WhatsappIcon} size={22} />
            </div>
            <h3 className="text-lg font-semibold tracking-tight">Sin canales todavía</h3>
            <p className="text-sm text-muted-foreground">
              Conecta tu primer canal para empezar a operar la bandeja.
            </p>
            <div className="flex justify-center pt-2">
              <Button onClick={() => setOpen(true)}>
                <HugeiconsIcon icon={PlusSignIcon} size={16} />
                Conectar WhatsApp
              </Button>
            </div>
          </CardHeader>
        </Card>
      )}

      <div className="space-y-3">
        {channels.data?.map((c) => <ChannelRow key={c.id} channel={c} />)}
      </div>

      <ConnectChannelDialog open={open} onClose={() => setOpen(false)} />
    </main>
  );
}
