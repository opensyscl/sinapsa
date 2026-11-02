"use client";

import { useState } from "react";
import Link from "next/link";
import dayjs from "dayjs";
import { toast } from "sonner";
import { HugeiconsIcon } from "@hugeicons/react";
import { Copy01Icon, Delete02Icon, PlayIcon } from "@hugeicons/core-free-icons";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { Card } from "@/components/ui/Card";
import { Input, Label } from "@/components/ui/Input";
import { api } from "@/lib/api";
import { useIntegrations } from "@/lib/queries/integrations";
import { cn } from "@/lib/utils";

/**
 * Probador de la API de Google Calendar: ejecuta los endpoints públicos
 * (/api/v1/integrations/{id}/calendar/*) con la sesión del dashboard y muestra
 * la respuesta cruda + el curl equivalente con una `sk_`.
 */
const ENDPOINTS = [
  { id: "availability", method: "GET", label: "Disponibilidad", path: "/calendar/availability" },
  { id: "events", method: "GET", label: "Listar eventos", path: "/calendar/events" },
  { id: "create", method: "POST", label: "Crear evento", path: "/calendar/events" },
  { id: "delete", method: "DELETE", label: "Borrar evento", path: "/calendar/events/{event_id}" },
] as const;
type EndpointId = (typeof ENDPOINTS)[number]["id"];

type Result = { status: number; ms: number; data: unknown };
type CalendarEvent = { id: string; summary: string | null; start: string | null };

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:48000";

// datetime-local ↔ ISO con offset del navegador
const toLocalInput = (d: dayjs.Dayjs) => d.format("YYYY-MM-DDTHH:mm");
const toIso = (v: string) => dayjs(v).format();

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="space-y-1.5">
      <Label>{label}</Label>
      {children}
    </div>
  );
}

export default function ProbarApiPage() {
  const integrations = useIntegrations();
  const calendars = (integrations.data ?? []).filter((i) => i.products.includes("calendar"));

  const [integrationId, setIntegrationId] = useState<number | null>(null);
  const [endpoint, setEndpoint] = useState<EndpointId>("availability");
  const [running, setRunning] = useState(false);
  const [result, setResult] = useState<Result | null>(null);

  const now = dayjs().startOf("hour");
  const [calendarId, setCalendarId] = useState("primary");
  const [timeMin, setTimeMin] = useState(toLocalInput(now));
  const [timeMax, setTimeMax] = useState(toLocalInput(now.add(7, "day")));
  const [limit, setLimit] = useState("10");
  const [summary, setSummary] = useState("Reunión de prueba (Sinapsa)");
  const [description, setDescription] = useState("");
  const [start, setStart] = useState(toLocalInput(now.add(1, "day")));
  const [end, setEnd] = useState(toLocalInput(now.add(1, "day").add(30, "minute")));
  const [attendee, setAttendee] = useState("");
  const [eventId, setEventId] = useState("");

  // Sin elección explícita, la primera cuenta conectada
  const selectedId = integrationId ?? calendars[0]?.id ?? null;
  const base = `/api/v1/integrations/${selectedId ?? "{id}"}`;

  // Request armada a partir del formulario
  const request = (() => {
    const cal = calendarId && calendarId !== "primary" ? calendarId : undefined;
    switch (endpoint) {
      case "availability":
        return {
          method: "GET",
          path: `${base}/calendar/availability`,
          params: { time_min: toIso(timeMin), time_max: toIso(timeMax), ...(cal && { calendar_ids: [cal] }) },
        };
      case "events":
        return {
          method: "GET",
          path: `${base}/calendar/events`,
          params: { time_min: toIso(timeMin), time_max: toIso(timeMax), limit: Number(limit) || 10, ...(cal && { calendar_id: cal }) },
        };
      case "create":
        return {
          method: "POST",
          path: `${base}/calendar/events`,
          body: {
            summary,
            ...(description && { description }),
            start: toIso(start),
            end: toIso(end),
            time_zone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            ...(attendee && { attendees: [{ email: attendee }] }),
            ...(cal && { calendar_id: cal }),
          },
        };
      case "delete":
        return {
          method: "DELETE",
          path: `${base}/calendar/events/${eventId || "{event_id}"}`,
          params: cal ? { calendar_id: cal } : undefined,
        };
    }
  })() as { method: string; path: string; params?: Record<string, unknown>; body?: Record<string, unknown> };

  const query = request.params
    ? "?" +
      Object.entries(request.params)
        .flatMap(([k, v]) =>
          Array.isArray(v) ? v.map((x) => `${k}[]=${encodeURIComponent(x)}`) : [`${k}=${encodeURIComponent(String(v))}`],
        )
        .join("&")
    : "";

  const curl = [
    `curl -X ${request.method} '${API_URL}${request.path}${query}'`,
    `  -H 'Authorization: Bearer sk_test_xxx'`,
    `  -H 'Accept: application/json'`,
    ...(request.body
      ? [`  -H 'Content-Type: application/json'`, `  -d '${JSON.stringify(request.body)}'`]
      : []),
  ].join(" \\\n");

  const run = async () => {
    if (!selectedId) return;
    if (endpoint === "delete" && !eventId) return toast.error("Falta el event_id");
    setRunning(true);
    const t0 = performance.now();
    try {
      const r = await api.request({
        method: request.method,
        url: request.path,
        params: request.params,
        data: request.body,
        validateStatus: () => true,
      });
      setResult({ status: r.status, ms: Math.round(performance.now() - t0), data: r.data });
      if (endpoint === "create" && r.status === 201 && r.data?.id) setEventId(r.data.id);
    } catch (e) {
      setResult({ status: 0, ms: Math.round(performance.now() - t0), data: { error: String(e) } });
    } finally {
      setRunning(false);
    }
  };

  const listed: CalendarEvent[] =
    endpoint === "events" && result?.status === 200
      ? ((result.data as { data?: CalendarEvent[] }).data ?? [])
      : [];

  return (
    <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-6 py-10">
      <div className="space-y-1">
        <h1 className="text-2xl font-semibold tracking-tight">Probar API</h1>
        <p className="text-sm text-muted-foreground">
          Prueba la API de Google Calendar contra una cuenta conectada. Las llamadas son reales.
        </p>
      </div>

      {!integrations.isLoading && calendars.length === 0 ? (
        <Card className="p-8 text-sm text-muted-foreground">
          No hay ninguna cuenta de Google Calendar conectada en este workspace. Conéctala desde la
          demo <code>demo/saas-x</code> (Connect Session → botón Google) y vuelve aquí.{" "}
          <Link href="/integraciones" className="font-medium text-foreground underline-offset-4 hover:underline">
            Ver integraciones
          </Link>
        </Card>
      ) : (
        <div className="grid gap-6 lg:grid-cols-2">
          <Card className="space-y-5 p-6">
            <Field label="Cuenta">
              <select
                value={selectedId ?? ""}
                onChange={(e) => setIntegrationId(Number(e.target.value))}
                className="w-full rounded-2xl border border-border bg-background px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
              >
                {calendars.map((i) => (
                  <option key={i.id} value={i.id}>
                    {i.email ?? i.display_name ?? `Google #${i.id}`}
                    {i.end_user_id ? ` · ${i.end_user_id}` : ""}
                    {i.status !== "connected" ? ` (${i.status})` : ""}
                  </option>
                ))}
              </select>
            </Field>

            <div className="flex flex-wrap gap-2">
              {ENDPOINTS.map((e) => (
                <button
                  key={e.id}
                  type="button"
                  onClick={() => {
                    setEndpoint(e.id);
                    setResult(null);
                  }}
                  aria-pressed={endpoint === e.id}
                  className={cn(
                    "inline-flex h-8 items-center gap-1.5 rounded-full border px-3 text-xs font-medium transition-colors",
                    endpoint === e.id
                      ? "border-foreground bg-foreground text-background"
                      : "border-border bg-background hover:bg-muted",
                  )}
                >
                  <span className="font-mono text-[10px] opacity-70">{e.method}</span>
                  {e.label}
                </button>
              ))}
            </div>

            <Field label="Calendario">
              <Input value={calendarId} onChange={(e) => setCalendarId(e.target.value)} placeholder="primary" />
            </Field>

            {(endpoint === "availability" || endpoint === "events") && (
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Desde">
                  <Input type="datetime-local" value={timeMin} onChange={(e) => setTimeMin(e.target.value)} />
                </Field>
                <Field label="Hasta">
                  <Input type="datetime-local" value={timeMax} onChange={(e) => setTimeMax(e.target.value)} />
                </Field>
                {endpoint === "events" && (
                  <Field label="Límite">
                    <Input type="number" min={1} max={250} value={limit} onChange={(e) => setLimit(e.target.value)} />
                  </Field>
                )}
              </div>
            )}

            {endpoint === "create" && (
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="sm:col-span-2">
                  <Field label="Título">
                    <Input value={summary} onChange={(e) => setSummary(e.target.value)} />
                  </Field>
                </div>
                <div className="sm:col-span-2">
                  <Field label="Descripción">
                    <Input value={description} onChange={(e) => setDescription(e.target.value)} placeholder="Opcional" />
                  </Field>
                </div>
                <Field label="Inicio">
                  <Input type="datetime-local" value={start} onChange={(e) => setStart(e.target.value)} />
                </Field>
                <Field label="Fin">
                  <Input type="datetime-local" value={end} onChange={(e) => setEnd(e.target.value)} />
                </Field>
                <div className="sm:col-span-2">
                  <Field label="Invitado">
                    <Input type="email" value={attendee} onChange={(e) => setAttendee(e.target.value)} placeholder="Opcional: email" />
                  </Field>
                </div>
              </div>
            )}

            {endpoint === "delete" && (
              <Field label="event_id">
                <Input value={eventId} onChange={(e) => setEventId(e.target.value)} placeholder="Lo rellena «Crear evento» o la lista" />
              </Field>
            )}

            <Button onClick={run} disabled={running || !selectedId} className="w-full">
              <HugeiconsIcon icon={PlayIcon} size={16} />
              {running ? "Enviando…" : "Enviar"}
            </Button>
          </Card>

          <div className="space-y-6">
            <Card className="space-y-3 p-6">
              <div className="flex items-center justify-between">
                <h2 className="text-sm font-medium text-muted-foreground">Request (con tu sk_)</h2>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => navigator.clipboard.writeText(curl).then(() => toast.success("curl copiado"))}
                >
                  <HugeiconsIcon icon={Copy01Icon} size={14} />
                  Copiar
                </Button>
              </div>
              <pre className="overflow-x-auto rounded-2xl bg-muted p-4 font-mono text-xs leading-relaxed">{curl}</pre>
            </Card>

            <Card className="space-y-3 p-6">
              <div className="flex items-center gap-2">
                <h2 className="text-sm font-medium text-muted-foreground">Response</h2>
                {result && (
                  <>
                    <Badge tone={result.status >= 200 && result.status < 300 ? "positive" : "destructive"}>
                      {result.status || "error"}
                    </Badge>
                    <span className="text-xs text-muted-foreground">{result.ms} ms</span>
                  </>
                )}
              </div>

              {listed.length > 0 && (
                <ul className="divide-y divide-border rounded-2xl border border-border">
                  {listed.map((e) => (
                    <li key={e.id} className="flex items-center gap-3 px-4 py-2.5 text-sm">
                      <span className="w-24 shrink-0 text-xs text-muted-foreground">{e.start ? dayjs(e.start).format("DD/MM HH:mm") : ""}</span>
                      <span className="flex-1 truncate">{e.summary ?? "(sin título)"}</span>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                          setEventId(e.id);
                          setEndpoint("delete");
                          setResult(null);
                        }}
                      >
                        <HugeiconsIcon icon={Delete02Icon} size={14} />
                        Borrar
                      </Button>
                    </li>
                  ))}
                </ul>
              )}

              <pre className="max-h-[480px] overflow-auto rounded-2xl bg-muted p-4 font-mono text-xs leading-relaxed">
                {result ? JSON.stringify(result.data, null, 2) : "Envía una request para ver la respuesta."}
              </pre>
            </Card>
          </div>
        </div>
      )}
    </main>
  );
}
