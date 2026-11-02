"use client";

import { Suspense, useState } from "react";
import { useSearchParams } from "next/navigation";
import dayjs from "dayjs";
import { HugeiconsIcon } from "@hugeicons/react";
import { ArrowDown01Icon, ArrowRight01Icon } from "@hugeicons/core-free-icons";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Page, PageHeader } from "@/components/app/Page";
import { useChannels } from "@/lib/queries/channels";
import { useIntegrations } from "@/lib/queries/integrations";
import { useRequestLogs, useWebhookLogs, type ApiRequestLog, type WebhookLog } from "@/lib/queries/logs";
import { cn } from "@/lib/utils";

type Tab = "requests" | "webhooks";
type StatusFilter = "all" | "ok" | "error";

export default function LogsPage() {
  return (
    <Suspense>
      <LogsView />
    </Suspense>
  );
}

function LogsView() {
  const params = useSearchParams();
  const initial = params.get("channel_id")
    ? `c:${params.get("channel_id")}`
    : params.get("integration_id")
      ? `i:${params.get("integration_id")}`
      : "";

  const [tab, setTab] = useState<Tab>("requests");
  const [connection, setConnection] = useState(initial);
  const [status, setStatus] = useState<StatusFilter>("all");
  const [page, setPage] = useState(1);

  const channels = useChannels();
  const integrations = useIntegrations();

  const [kind, rawId] = connection.split(":");
  const id = rawId ? Number(rawId) : undefined;
  const channelId = kind === "c" ? id : undefined;
  const integrationId = kind === "i" ? id : undefined;

  const reset = <T,>(set: (v: T) => void) => (v: T) => {
    set(v);
    setPage(1);
  };

  const segment = (value: string, current: string, label: string, onClick: () => void) => (
    <button
      key={value}
      type="button"
      onClick={onClick}
      aria-pressed={current === value}
      className={cn(
        "h-8 rounded-md px-3 text-sm transition-colors",
        current === value ? "bg-active font-medium text-foreground" : "text-muted-foreground hover:text-foreground",
      )}
    >
      {label}
    </button>
  );

  return (
    <Page className="gap-6">
      <PageHeader title="Registros" subtitle="Cada llamada a la API y cada webhook de Meta, por conexión. Se guardan 30 días." />

      <div className="space-y-5">
        <div className="flex gap-6 border-b border-border" role="tablist">
          {(
            [
              ["requests", "Llamadas a la API"],
              ["webhooks", "Webhooks de Meta"],
            ] as const
          ).map(([value, label]) => (
            <button
              key={value}
              type="button"
              role="tab"
              aria-selected={tab === value}
              onClick={() => reset(setTab)(value)}
              className={cn(
                "-mb-px border-b-2 pb-2.5 text-sm transition-colors",
                tab === value ? "border-foreground font-medium text-foreground" : "border-transparent text-muted-foreground hover:text-foreground",
              )}
            >
              {label}
            </button>
          ))}
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <select
            value={connection}
            onChange={(e) => reset(setConnection)(e.target.value)}
            aria-label="Conexión"
            className="h-8 rounded-md border border-[#d1d5db] bg-background px-2.5 text-sm"
          >
            <option value="">Todas las conexiones</option>
            {channels.data?.map((c) => (
              <option key={`c${c.id}`} value={`c:${c.id}`}>
                {c.type} · {c.display_name}
              </option>
            ))}
            {tab === "requests" &&
              integrations.data?.map((i) => (
                <option key={`i${i.id}`} value={`i:${i.id}`}>
                  Google Calendar · {i.email ?? i.display_name ?? `#${i.id}`}
                </option>
              ))}
          </select>

          {tab === "requests" && (
            <div className="flex items-center gap-0.5">
              {segment("all", status, "Todas", () => reset(setStatus)("all"))}
              {segment("ok", status, "Correctas", () => reset(setStatus)("ok"))}
              {segment("error", status, "Con error", () => reset(setStatus)("error"))}
            </div>
          )}
        </div>

        {tab === "requests" ? (
          <RequestList
            filters={{
              channel_id: channelId,
              integration_id: integrationId,
              status: status === "all" ? undefined : status,
              page,
            }}
            onPage={setPage}
          />
        ) : (
          <WebhookList filters={{ channel_id: channelId, page }} onPage={setPage} />
        )}
      </div>
    </Page>
  );
}

function RequestList({
  filters,
  onPage,
}: {
  filters: Parameters<typeof useRequestLogs>[0];
  onPage: (p: number) => void;
}) {
  const logs = useRequestLogs(filters);

  if (logs.isLoading) return <Empty text="Cargando…" />;
  if (!logs.data?.data.length)
    return <Empty text="No hay llamadas con este filtro. Usa la API o Probar API y aparecerán aquí." />;

  return (
    <div className="space-y-4">
      <div role="table" className="text-sm">
        <div role="row" className="hidden grid-cols-[16px_minmax(0,1fr)_88px_80px_150px_110px] gap-4 py-2 text-xs text-muted-foreground lg:grid">
          <span />
          <span>Llamada</span>
          <span className="text-right">Estado</span>
          <span className="text-right">Tiempo</span>
          <span>Origen</span>
          <span className="text-right">Fecha</span>
        </div>
        <div className="divide-y divide-rule border-t border-border">
          {logs.data.data.map((l) => (
            <RequestRow key={l.id} log={l} />
          ))}
        </div>
      </div>
      <Pager meta={logs.data.meta} onPage={onPage} />
    </div>
  );
}

function RequestRow({ log }: { log: ApiRequestLog }) {
  const [open, setOpen] = useState(false);
  const ok = log.status < 400;

  return (
    <div>
      <button
        type="button"
        onClick={() => setOpen(!open)}
        aria-expanded={open}
        className={cn(
          "grid w-full grid-cols-[16px_minmax(0,1fr)_auto] items-center gap-x-2 gap-y-0.5 py-3 text-left hover:bg-frame lg:grid-cols-[16px_minmax(0,1fr)_88px_80px_150px_110px] lg:gap-4",
          open && "bg-frame",
        )}
      >
        <HugeiconsIcon icon={open ? ArrowDown01Icon : ArrowRight01Icon} size={12} className="text-muted-foreground" />
        <code className="truncate text-[13px]">
          <span className="font-semibold">{log.method}</span> {log.path}
        </code>
        <span className="text-right">
          <Badge tone={ok ? "positive" : "destructive"} className="tabular-nums">{log.status}</Badge>
        </span>
        <span className="col-start-2 text-xs text-muted-foreground tabular-nums lg:hidden">
          {log.duration_ms} ms · {log.caller} · {dayjs(log.created_at).format("DD/MM HH:mm")}
        </span>
        <span className="hidden text-right tabular-nums text-muted-foreground lg:block">{log.duration_ms} ms</span>
        <span className="hidden truncate text-muted-foreground lg:block">{log.caller}</span>
        <span className="hidden text-right tabular-nums text-muted-foreground lg:block">
          {dayjs(log.created_at).format("DD/MM HH:mm:ss")}
        </span>
      </button>

      {open && (
        <div className="space-y-3 bg-frame px-6 pb-5">
          <p className="text-xs text-muted-foreground tabular-nums">
            {dayjs(log.created_at).format("DD/MM/YYYY HH:mm:ss")} · {log.duration_ms} ms · {log.caller}
            {log.ip && <> · IP {log.ip}</>}
            {log.error_code && (
              <>
                {" · "}
                <span className="text-destructive">{log.error_code}</span>
              </>
            )}
          </p>
          <div className="grid gap-3 lg:grid-cols-2">
            <JsonBlock title="Petición" value={log.request_body} />
            <JsonBlock title="Respuesta" value={log.response_body} />
          </div>
        </div>
      )}
    </div>
  );
}

function WebhookList({ filters, onPage }: { filters: { channel_id?: number; page: number }; onPage: (p: number) => void }) {
  const logs = useWebhookLogs(filters);

  if (logs.isLoading) return <Empty text="Cargando…" />;
  if (!logs.data?.data.length) return <Empty text="Todavía no llegan webhooks de Meta para esta conexión." />;

  return (
    <>
      <div className="divide-y divide-rule border-t border-border">
        {logs.data.data.map((l) => (
          <WebhookRow key={l.id} log={l} />
        ))}
      </div>
      <Pager meta={logs.data.meta} onPage={onPage} />
    </>
  );
}

function WebhookRow({ log }: { log: WebhookLog }) {
  const [open, setOpen] = useState(false);
  const state = log.processing_error ? "error" : log.processed_at ? "ok" : "pending";

  return (
    <div>
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className="flex w-full items-center gap-4 py-3 text-left text-sm hover:bg-frame"
      >
        <HugeiconsIcon icon={open ? ArrowDown01Icon : ArrowRight01Icon} size={12} className="text-muted-foreground" />
        <span className="w-24 font-medium capitalize">{log.source}</span>
        <Badge tone={state === "ok" ? "positive" : state === "error" ? "destructive" : "warning"}>
          {state === "ok" ? "Procesado" : state === "error" ? "Error" : "Pendiente"}
        </Badge>
        {!log.signature_valid && <Badge tone="destructive">Firma inválida</Badge>}
        <span className="ml-auto text-xs text-muted-foreground">{dayjs(log.created_at).format("DD/MM HH:mm:ss")}</span>
      </button>
      {open && (
        <div className="space-y-3 bg-frame px-6 pb-5">
          {log.processing_error && <p className="text-xs text-destructive">{log.processing_error}</p>}
          <JsonBlock title="Payload de Meta" value={log.payload} />
        </div>
      )}
    </div>
  );
}

function JsonBlock({ title, value }: { title: string; value: unknown }) {
  return (
    <div className="min-w-0 space-y-1.5">
      <p className="text-xs font-medium text-muted-foreground">{title}</p>
      <pre className="max-h-80 overflow-auto rounded-lg border border-border bg-background p-4 font-mono text-xs leading-relaxed">
        {value == null ? "—" : JSON.stringify(value, null, 2)}
      </pre>
    </div>
  );
}

function Pager({ meta, onPage }: { meta: { current_page: number; last_page: number; total: number }; onPage: (p: number) => void }) {
  return (
    <div className="flex items-center justify-between text-sm text-muted-foreground">
      <span className="tabular-nums">{meta.total.toLocaleString("es")} registros</span>
      {meta.last_page > 1 && (
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>
            Anterior
          </Button>
          <span>
            {meta.current_page} / {meta.last_page}
          </span>
          <Button
            variant="outline"
            size="sm"
            disabled={meta.current_page >= meta.last_page}
            onClick={() => onPage(meta.current_page + 1)}
          >
            Siguiente
          </Button>
        </div>
      )}
    </div>
  );
}

function Empty({ text }: { text: string }) {
  return <p className="rounded-lg border border-dashed border-border px-4 py-3 text-sm text-muted-foreground">{text}</p>;
}
