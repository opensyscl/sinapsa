"use client";

import Link from "next/link";
import dayjs from "dayjs";
import { HugeiconsIcon } from "@hugeicons/react";
import { ArrowRight01Icon, CheckmarkCircle01Icon } from "@hugeicons/core-free-icons";
import { HourlyBars } from "@/components/app/HourlyBars";
import { Page, PageHeader, Section } from "@/components/app/Page";
import { Badge } from "@/components/ui/Badge";
import { buttonVariants } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { useApiTokens } from "@/lib/queries/api-tokens";
import { useChannels } from "@/lib/queries/channels";
import { useIntegrations } from "@/lib/queries/integrations";
import { useLogSummary, useRequestLogs } from "@/lib/queries/logs";
import { useWebhookEndpoints } from "@/lib/queries/webhooks";
import { ago } from "@/lib/time";
import { cn } from "@/lib/utils";

const CHANNEL_LABEL = { whatsapp: "WhatsApp", instagram: "Instagram", messenger: "Messenger" } as const;
const fmt = (n: number) => n.toLocaleString("es");

/**
 * Inicio. Pregunta: ¿están funcionando mis conexiones y mis llamadas?
 * Lo más grande es la actividad de las últimas 24 h, al lado la salud de cada conexión.
 */
export default function DashboardPage() {
  const channels = useChannels();
  const integrations = useIntegrations();
  const summary = useLogSummary();
  const recent = useRequestLogs({ per_page: 5 });
  const tokens = useApiTokens();
  const webhooks = useWebhookEndpoints();

  // Todas las conexiones, con su actividad de 24 h (cero si no hubo llamadas)
  const activity = summary.data?.by_connection ?? [];
  const connections = [
    ...(channels.data ?? []).map((c) => ({
      key: `c${c.id}`,
      name: c.display_name,
      kind: CHANNEL_LABEL[c.type],
      connected: c.status === "connected",
      href: `/registros?channel_id=${c.id}`,
      stats: activity.find((a) => a.channel_id === c.id),
    })),
    ...(integrations.data ?? []).map((i) => ({
      key: `i${i.id}`,
      name: i.email ?? i.display_name ?? `Google #${i.id}`,
      kind: "Google Calendar",
      connected: i.status === "connected",
      href: `/registros?integration_id=${i.id}`,
      stats: activity.find((a) => a.integration_id === i.id),
    })),
  ].sort((a, b) => (b.stats?.calls ?? 0) - (a.stats?.calls ?? 0));

  const active = connections.filter((c) => c.connected).length;
  const totals = summary.data?.totals;

  const steps = [
    { label: "Crea una clave de API", text: "Para llamar a Sinapsa desde tu backend.", href: "/desarrolladores", done: (tokens.data?.data.length ?? 0) > 0 },
    { label: "Conecta una cuenta", text: "WhatsApp, Instagram, Messenger o Google Calendar.", href: "/integraciones", done: connections.length > 0 },
    { label: "Haz tu primera llamada", text: "Desde tu código o desde Probar API.", href: "/probar-api", done: (recent.data?.meta.total ?? 0) > 0 },
    { label: "Recibe eventos por webhook", text: "Mensajes entrantes y estados, en tu URL.", href: "/desarrolladores", done: (webhooks.data?.data.length ?? 0) > 0 },
  ];
  const loaded = !tokens.isLoading && !webhooks.isLoading && !channels.isLoading && !integrations.isLoading && !recent.isLoading;
  const pending = steps.filter((s) => !s.done).length;

  const scope = connections.length === 0 ? "Sin cuentas conectadas" : `${active} de ${connections.length} conexiones activas`;
  const finding = !totals
    ? "Cargando actividad…"
    : totals.calls === 0
      ? `${scope} · sin llamadas en las últimas 24 h`
      : `${scope} · ${fmt(totals.errors)} de ${fmt(totals.calls)} llamadas con error en las últimas 24 h`;

  return (
    <Page>
      <PageHeader
        title="Inicio"
        subtitle={finding}
        actions={
          <>
            {summary.dataUpdatedAt > 0 && (
              <span className="text-sm text-muted-foreground tabular-nums">
                Actualizado {dayjs(summary.dataUpdatedAt).format("HH:mm")}
              </span>
            )}
            <Link href="/integraciones" className={buttonVariants({ size: "sm" })}>
              Conectar una cuenta
            </Link>
          </>
        }
      />

      {loaded && pending > 0 && (
        <Section title="Primeros pasos" meta={`${steps.length - pending} de ${steps.length}`}>
          <Card className="grid divide-y divide-border sm:grid-cols-2 sm:divide-y-0 lg:grid-cols-4 lg:divide-x">
            {steps.map((s) => (
              <Link key={s.label} href={s.href} className="group flex items-start gap-3 p-5 hover:bg-frame">
                <div className="min-w-0 flex-1 space-y-1">
                  <p className={cn("text-sm font-medium", s.done && "text-muted-foreground line-through")}>{s.label}</p>
                  <p className="text-sm text-muted-foreground">{s.text}</p>
                </div>
                <HugeiconsIcon
                  icon={s.done ? CheckmarkCircle01Icon : ArrowRight01Icon}
                  size={16}
                  className={s.done ? "text-positive" : "text-muted-foreground group-hover:text-foreground"}
                />
              </Link>
            ))}
          </Card>
        </Section>
      )}

      <Section title="Actividad" meta="últimas 24 h">
        <div className="space-y-1.5 rounded-xl bg-frame p-1.5">
          <Card className="grid grid-cols-2 divide-border lg:grid-cols-4 lg:divide-x">
            <Kpi label="Llamadas a la API" value={totals ? fmt(totals.calls) : null} />
            <Kpi
              label="Con error"
              value={totals ? fmt(totals.errors) : null}
              note={totals && totals.calls > 0 ? `${((totals.errors / totals.calls) * 100).toFixed(1)}% del total` : undefined}
              bad={!!totals && totals.errors > 0}
            />
            <Kpi label="Tiempo medio" value={totals?.avg_ms != null ? fmt(totals.avg_ms) : totals ? "—" : null} unit="ms" />
            <Kpi label="Webhooks de Meta" value={totals ? fmt(totals.webhooks) : null} />
          </Card>

          <div className="grid gap-1.5 lg:grid-cols-[2fr_1fr]">
            <Card className="p-5">
              <div className="mb-4 flex items-baseline justify-between gap-3">
                <h3 className="text-sm font-medium">Llamadas por hora</h3>
                <span className="flex items-center gap-3 text-xs text-muted-foreground">
                  <span className="flex items-center gap-1.5"><i className="inline-block h-2 w-2 bg-data" />Correctas</span>
                  <span className="flex items-center gap-1.5"><i className="inline-block h-2 w-2 bg-destructive" />Con error</span>
                </span>
              </div>
              {summary.data ? <HourlyBars data={summary.data.hourly} /> : <div className="h-44 rounded bg-frame" />}
            </Card>

            <Card className="flex flex-col p-5">
              <div className="mb-2 flex items-baseline justify-between">
                <h3 className="text-sm font-medium">Por conexión</h3>
                <Link href="/canales" className="text-sm text-muted-foreground hover:text-foreground">
                  Ver todas
                </Link>
              </div>
              {connections.length === 0 ? (
                <ul className="divide-y divide-rule">
                  {["WhatsApp", "Instagram", "Messenger", "Google Calendar"].map((kind) => (
                    <li key={kind} className="flex items-center justify-between py-2.5 text-sm">
                      <span>{kind}</span>
                      <span className="flex items-center gap-3">
                        <span className="text-muted-foreground tabular-nums">0</span>
                        <Link href="/integraciones" className="font-medium hover:underline">
                          Conectar
                        </Link>
                      </span>
                    </li>
                  ))}
                </ul>
              ) : (
                <ul className="divide-y divide-rule">
                  {connections.slice(0, 5).map((c) => (
                    <li key={c.key}>
                      <Link href={c.href} className="flex items-center gap-3 py-2.5 hover:bg-frame">
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm font-medium">{c.name}</p>
                          <p className="truncate text-xs text-muted-foreground">
                            {c.kind}
                            {!c.connected && " · desconectada"} · {c.stats ? `última ${ago(c.stats.last_at)}` : "sin llamadas"}
                          </p>
                        </div>
                        <div className="text-right tabular-nums">
                          <p className="text-sm">{fmt(c.stats?.calls ?? 0)}</p>
                          <p className={cn("text-xs", c.stats?.errors ? "text-destructive" : "text-muted-foreground")}>
                            {c.stats?.errors ? `${fmt(c.stats.errors)} con error` : "sin errores"}
                          </p>
                        </div>
                      </Link>
                    </li>
                  ))}
                </ul>
              )}
            </Card>
          </div>
        </div>
      </Section>

      <Section
        title="Últimas llamadas"
        meta={recent.data ? `${fmt(recent.data.meta.total)} en 30 días` : undefined}
        action={
          <Link href="/registros" className="text-sm text-muted-foreground hover:text-foreground">
            Ver todas
          </Link>
        }
      >
        {recent.data?.data.length ? (
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-xs text-muted-foreground">
                <th className="py-2 pr-4 font-normal">Llamada</th>
                <th className="hidden py-2 pr-4 font-normal md:table-cell">Origen</th>
                <th className="py-2 pr-4 text-right font-normal">Estado</th>
                <th className="hidden py-2 pr-4 text-right font-normal sm:table-cell">Tiempo</th>
                <th className="py-2 text-right font-normal">Cuándo</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-rule">
              {recent.data.data.map((l) => (
                <tr key={l.id}>
                  <td className="max-w-0 py-3 pr-4">
                    <code className="block truncate text-[13px]">
                      <span className="font-semibold">{l.method}</span> {l.path}
                    </code>
                  </td>
                  <td className="hidden py-3 pr-4 text-muted-foreground md:table-cell">{l.caller}</td>
                  <td className="py-3 pr-4 text-right">
                    <Badge tone={l.status < 400 ? "positive" : "destructive"} className="tabular-nums">{l.status}</Badge>
                  </td>
                  <td className="hidden py-3 pr-4 text-right tabular-nums text-muted-foreground sm:table-cell">
                    {l.duration_ms} ms
                  </td>
                  <td className="py-3 text-right tabular-nums text-muted-foreground">{ago(l.created_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p className="rounded-lg border border-dashed border-border px-4 py-3 text-sm text-muted-foreground">
            {recent.isLoading ? "Cargando…" : "Aún no hay llamadas. Haz la primera desde Probar API."}
          </p>
        )}
      </Section>
    </Page>
  );
}

function Kpi({
  label,
  value,
  unit,
  note,
  bad,
}: {
  label: string;
  value: string | null;
  unit?: string;
  note?: string;
  bad?: boolean;
}) {
  return (
    <div className="space-y-1 p-5">
      <p className="text-sm text-muted-foreground">{label}</p>
      {value === null ? (
        <div className="h-7 w-16 rounded bg-frame" />
      ) : (
        <p className={cn("text-[28px] leading-none tabular-nums", bad && "text-destructive")}>
          {value}
          {unit && value !== "—" && <span className="ml-1 text-sm text-muted-foreground">{unit}</span>}
        </p>
      )}
      {note && <p className="text-xs text-muted-foreground tabular-nums">{note}</p>}
    </div>
  );
}
