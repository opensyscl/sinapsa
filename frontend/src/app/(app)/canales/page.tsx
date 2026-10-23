"use client";

import { useMemo, useState } from "react";
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
  GoogleIcon,
  Calendar03Icon,
  UserIcon,
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
import {
  useIntegrations,
  useDisconnectIntegration,
  type Integration,
} from "@/lib/queries/integrations";
import { ConnectChannelDialog } from "@/components/channels/ConnectChannelDialog";
import { cn } from "@/lib/utils";
import dayjs from "dayjs";

const channelIcon = {
  whatsapp: WhatsappIcon,
  instagram: InstagramIcon,
  messenger: FacebookIcon,
} as const;

// Filtro por usuario final del cliente SaaS
const ALL = "__all__";
const NONE = "__none__";

// Filtro por tipo de conexión
const TYPE_FILTERS = [
  { id: "whatsapp", label: "WhatsApp", icon: WhatsappIcon },
  { id: "instagram", label: "Instagram", icon: InstagramIcon },
  { id: "messenger", label: "Messenger", icon: FacebookIcon },
  { id: "google_calendar", label: "Google Calendar", icon: Calendar03Icon },
] as const;
type TypeFilter = (typeof TYPE_FILTERS)[number]["id"] | typeof ALL;

const integrationType = (i: Integration): TypeFilter =>
  i.products.includes("calendar") ? "google_calendar" : ALL;

function FilterChip({
  active,
  onClick,
  icon,
  label,
  count,
}: {
  active: boolean;
  onClick: () => void;
  icon?: typeof UserIcon;
  label: string;
  count: number;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={cn(
        "inline-flex h-8 items-center gap-1.5 rounded-full border px-3 text-xs font-medium transition-colors",
        active
          ? "border-foreground bg-foreground text-background"
          : "border-border bg-background text-foreground hover:bg-muted",
        !active && count === 0 && "text-muted-foreground",
      )}
    >
      {icon && <HugeiconsIcon icon={icon} size={14} />}
      {label}
      <span className={active ? "text-background/70" : "text-muted-foreground"}>{count}</span>
    </button>
  );
}

function EndUserBadge({ id }: { id: string | null }) {
  return (
    <Badge tone={id ? "outline" : "neutral"}>
      <HugeiconsIcon icon={UserIcon} size={12} />
      {id ?? "sin usuario"}
    </Badge>
  );
}

function StatusBadge({ status }: { status: Channel["status"] | Integration["status"] }) {
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
            <EndUserBadge id={channel.end_user_id} />
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

function IntegrationRow({ integration }: { integration: Integration }) {
  const disconnect = useDisconnectIntegration();
  const label = integration.display_name ?? integration.email ?? `Google #${integration.id}`;

  return (
    <Card className="p-5">
      <div className="flex flex-wrap items-start gap-4">
        <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-muted">
          <HugeiconsIcon icon={GoogleIcon} size={22} />
        </div>

        <div className="flex-1 min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-base font-semibold tracking-tight">{label}</h3>
            <StatusBadge status={integration.status} />
            <EndUserBadge id={integration.end_user_id} />
          </div>
          <p className="text-xs text-muted-foreground mt-1 truncate">
            Google {integration.products.map((p) => (p === "calendar" ? "Calendar" : p)).join(", ")}
            {integration.email && <> · {integration.email}</>}
          </p>
          <p className="text-xs text-muted-foreground mt-0.5">
            Conectado {dayjs(integration.created_at).format("DD/MM/YYYY HH:mm")}
          </p>
          {integration.status === "error" && integration.last_error_message && (
            <p className="mt-2 rounded-2xl border border-destructive/20 bg-destructive/5 px-3 py-2 text-xs text-destructive">
              {integration.last_error_code ? `[${integration.last_error_code}] ` : ""}
              {integration.last_error_message}
            </p>
          )}
        </div>

        <Button
          variant="ghost"
          size="sm"
          onClick={() => {
            if (!confirm(`¿Desconectar "${label}"? Se revoca el acceso en Google.`)) return;
            disconnect.mutate(integration.id, {
              onSuccess: () => toast.success("Integración desconectada"),
            });
          }}
          disabled={disconnect.isPending}
        >
          <HugeiconsIcon icon={Cancel01Icon} size={14} />
          Desconectar
        </Button>
      </div>
    </Card>
  );
}

export default function ChannelsPage() {
  const [open, setOpen] = useState(false);
  const channels = useChannels();
  const integrations = useIntegrations();
  const [endUser, setEndUser] = useState<string>(ALL);
  const [type, setType] = useState<TypeFilter>(ALL);

  // Usuarios finales con algo conectado, y cuántas conexiones tiene cada uno
  const endUsers = useMemo(() => {
    const counts = new Map<string, number>();
    for (const item of [...(channels.data ?? []), ...(integrations.data ?? [])]) {
      const key = item.end_user_id ?? NONE;
      counts.set(key, (counts.get(key) ?? 0) + 1);
    }
    return [...counts.entries()].sort(([a], [b]) =>
      a === NONE ? 1 : b === NONE ? -1 : a.localeCompare(b),
    );
  }, [channels.data, integrations.data]);

  const matchesUser = (id: string | null) =>
    endUser === ALL || (endUser === NONE ? id === null : id === endUser);
  const matchesType = (t: TypeFilter) => type === ALL || t === type;

  // Conteos por tipo respetando el filtro de usuario (y viceversa abajo)
  const typeCounts = useMemo(() => {
    const counts: Record<string, number> = {};
    for (const c of channels.data ?? []) {
      if (matchesUser(c.end_user_id)) counts[c.type] = (counts[c.type] ?? 0) + 1;
    }
    for (const i of integrations.data ?? []) {
      if (matchesUser(i.end_user_id)) {
        const t = integrationType(i);
        counts[t] = (counts[t] ?? 0) + 1;
      }
    }
    return counts;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [channels.data, integrations.data, endUser]);

  const visibleChannels =
    channels.data?.filter((c) => matchesUser(c.end_user_id) && matchesType(c.type)) ?? [];
  const visibleIntegrations =
    integrations.data?.filter((i) => matchesUser(i.end_user_id) && matchesType(integrationType(i))) ?? [];
  const totalForUser = Object.values(typeCounts).reduce((a, b) => a + b, 0);
  const isLoading = channels.isLoading || integrations.isLoading;
  const isEmpty = !isLoading && !channels.data?.length && !integrations.data?.length;

  return (
    <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-6 py-10">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-2xl font-semibold tracking-tight">Canales</h1>
          <p className="text-sm text-muted-foreground">
            Todo lo que tienen conectado tus usuarios: WhatsApp, Instagram, Messenger y Google Calendar.
          </p>
        </div>

        <Button onClick={() => setOpen(true)}>
          <HugeiconsIcon icon={PlusSignIcon} size={16} />
          Conectar canal
        </Button>
      </div>

      {!isEmpty && !isLoading && (
        <div className="space-y-3">
          <div className="flex flex-wrap items-center gap-2">
            <FilterChip active={type === ALL} onClick={() => setType(ALL)} label="Todos" count={totalForUser} />
            {TYPE_FILTERS.map((f) => (
              <FilterChip
                key={f.id}
                active={type === f.id}
                onClick={() => setType(f.id)}
                icon={f.icon}
                label={f.label}
                count={typeCounts[f.id] ?? 0}
              />
            ))}
          </div>

          {endUsers.length > 0 && (
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-xs text-muted-foreground">Usuario:</span>
              <FilterChip
                active={endUser === ALL}
                onClick={() => setEndUser(ALL)}
                label="Todos"
                count={(channels.data?.length ?? 0) + (integrations.data?.length ?? 0)}
              />
              {endUsers.map(([id, count]) => (
                <FilterChip
                  key={id}
                  active={endUser === id}
                  onClick={() => setEndUser(id)}
                  icon={UserIcon}
                  label={id === NONE ? "Sin usuario" : id}
                  count={count}
                />
              ))}
            </div>
          )}
        </div>
      )}

      {isLoading && (
        <Card className="p-8 text-center text-sm text-muted-foreground">Cargando…</Card>
      )}

      {isEmpty && (
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

      {visibleChannels.length > 0 && (
        <section className="space-y-3">
          <h2 className="text-sm font-medium text-muted-foreground">Mensajería</h2>
          {visibleChannels.map((c) => <ChannelRow key={c.id} channel={c} />)}
        </section>
      )}

      {visibleIntegrations.length > 0 && (
        <section className="space-y-3">
          <h2 className="text-sm font-medium text-muted-foreground">Calendario</h2>
          {visibleIntegrations.map((i) => <IntegrationRow key={i.id} integration={i} />)}
        </section>
      )}

      {!isLoading && !isEmpty && visibleChannels.length === 0 && visibleIntegrations.length === 0 && (
        <p className="py-10 text-center text-sm text-muted-foreground">
          Nada conectado con este filtro.
        </p>
      )}

      <ConnectChannelDialog open={open} onClose={() => setOpen(false)} />
    </main>
  );
}
