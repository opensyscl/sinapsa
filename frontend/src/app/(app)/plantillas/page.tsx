"use client";

import { useState } from "react";
import { toast } from "sonner";
import dayjs from "dayjs";
import { HugeiconsIcon } from "@hugeicons/react";
import {
  PlusSignIcon,
  Cancel01Icon,
  RefreshIcon,
  CheckmarkCircle01Icon,
  AlertCircleIcon,
  Clock01Icon,
} from "@hugeicons/core-free-icons";
import { Button } from "@/components/ui/Button";
import { Card, CardHeader } from "@/components/ui/Card";
import { Badge } from "@/components/ui/Badge";
import {
  useTemplates,
  useDeleteTemplate,
  useSyncChannelTemplates,
  type WaTemplate,
} from "@/lib/queries/templates";
import { useChannels } from "@/lib/queries/channels";
import { CreateTemplateDialog } from "@/components/templates/CreateTemplateDialog";

function StatusBadge({ status }: { status: WaTemplate["status"] }) {
  if (status === "APPROVED")
    return (
      <Badge tone="positive">
        <HugeiconsIcon icon={CheckmarkCircle01Icon} size={11} />
        APPROVED
      </Badge>
    );
  if (status === "REJECTED")
    return (
      <Badge tone="destructive">
        <HugeiconsIcon icon={AlertCircleIcon} size={11} />
        REJECTED
      </Badge>
    );
  if (status === "PENDING")
    return (
      <Badge tone="warning">
        <HugeiconsIcon icon={Clock01Icon} size={11} />
        PENDING
      </Badge>
    );
  if (status === "PAUSED") return <Badge tone="warning">PAUSED</Badge>;
  return <Badge tone="neutral">{status}</Badge>;
}

function TemplateRow({ template }: { template: WaTemplate }) {
  const del = useDeleteTemplate();
  const [expanded, setExpanded] = useState(false);

  const bodyComponent = template.components?.find((c) => c.type === "BODY");
  const footerComponent = template.components?.find((c) => c.type === "FOOTER");

  return (
    <Card className="p-5">
      <div className="flex flex-wrap items-start gap-4">
        <div className="flex-1 min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-base font-semibold tracking-tight">{template.name}</h3>
            <Badge tone="outline">{template.language}</Badge>
            {template.category && <Badge tone="neutral">{template.category}</Badge>}
            <StatusBadge status={template.status} />
          </div>
          <div className="mt-1 text-xs text-muted-foreground">
            {template.last_synced_at
              ? `Última sync ${dayjs(template.last_synced_at).format("DD/MM HH:mm")}`
              : "Sin sync"}
            {template.meta_template_id && (
              <>
                {" · "}meta_id <code>{template.meta_template_id}</code>
              </>
            )}
          </div>
          {template.rejected_reason && (
            <p className="mt-2 rounded-2xl border border-destructive/20 bg-destructive/5 px-3 py-2 text-xs text-destructive">
              <strong>Rechazada:</strong> {template.rejected_reason}
            </p>
          )}

          {expanded && bodyComponent?.text && (
            <Card className="mt-3 bg-muted/40 p-3">
              <p className="whitespace-pre-wrap text-sm">{bodyComponent.text}</p>
              {footerComponent?.text && (
                <p className="mt-2 text-xs text-muted-foreground">{footerComponent.text}</p>
              )}
            </Card>
          )}
        </div>

        <div className="flex items-center gap-2">
          {bodyComponent && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setExpanded((v) => !v)}
            >
              {expanded ? "Ocultar" : "Ver cuerpo"}
            </Button>
          )}
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              if (
                !confirm(
                  `¿Borrar plantilla "${template.name}"? Se eliminará también en Meta.`,
                )
              )
                return;
              del.mutate(template.id, {
                onSuccess: () => toast.success("Plantilla eliminada"),
                onError: () => toast.error("Error al eliminar"),
              });
            }}
            disabled={del.isPending}
          >
            <HugeiconsIcon icon={Cancel01Icon} size={14} />
            Borrar
          </Button>
        </div>
      </div>
    </Card>
  );
}

export default function TemplatesPage() {
  const [openCreate, setOpenCreate] = useState(false);
  const [filterChannelId, setFilterChannelId] = useState<number | undefined>();
  const [filterStatus, setFilterStatus] = useState<string>("");
  const channels = useChannels();
  const sync = useSyncChannelTemplates();
  const templates = useTemplates({
    channel_id: filterChannelId,
    status: filterStatus || undefined,
  });

  return (
    <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-6 py-10">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-2xl font-semibold tracking-tight">Plantillas WhatsApp</h1>
          <p className="text-sm text-muted-foreground">
            Plantillas aprobadas por Meta. Necesarias para enviar mensajes fuera de
            la ventana de 24h o marketing.
          </p>
        </div>
        <div className="flex gap-2">
          <Button
            variant="outline"
            onClick={() => {
              if (!filterChannelId) {
                toast.error("Selecciona un canal antes de sincronizar");
                return;
              }
              sync.mutate(filterChannelId, {
                onSuccess: (n) => toast.success(`${n} plantillas sincronizadas desde Meta`),
                onError: (e) => {
                  const err = e as { response?: { data?: { error?: { message?: string } } } };
                  toast.error(
                    err.response?.data?.error?.message ?? "Error al sincronizar",
                  );
                },
              });
            }}
            disabled={sync.isPending || !filterChannelId}
          >
            <HugeiconsIcon icon={RefreshIcon} size={14} />
            Sync con Meta
          </Button>
          <Button onClick={() => setOpenCreate(true)}>
            <HugeiconsIcon icon={PlusSignIcon} size={16} />
            Crear plantilla
          </Button>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <select
          value={filterChannelId ?? ""}
          onChange={(e) => setFilterChannelId(e.target.value ? Number(e.target.value) : undefined)}
          className="rounded-2xl border border-border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
        >
          <option value="">Todos los canales</option>
          {channels.data?.map((c) => (
            <option key={c.id} value={c.id}>
              {c.display_name}
            </option>
          ))}
        </select>
        <select
          value={filterStatus}
          onChange={(e) => setFilterStatus(e.target.value)}
          className="rounded-2xl border border-border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
        >
          <option value="">Todos los estados</option>
          {templates.data?.available_statuses.map((s) => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </select>
      </div>

      {templates.isLoading && (
        <Card className="p-8 text-center text-sm text-muted-foreground">Cargando…</Card>
      )}

      {templates.data?.data.length === 0 && (
        <Card className="p-10 text-center">
          <CardHeader className="space-y-3 text-center">
            <h3 className="text-lg font-semibold tracking-tight">Sin plantillas</h3>
            <p className="text-sm text-muted-foreground">
              Sincroniza con Meta o crea tu primera plantilla.
            </p>
            <div className="flex justify-center gap-2 pt-2">
              <Button onClick={() => setOpenCreate(true)}>
                <HugeiconsIcon icon={PlusSignIcon} size={16} />
                Crear plantilla
              </Button>
            </div>
          </CardHeader>
        </Card>
      )}

      <div className="space-y-3">
        {templates.data?.data.map((t) => <TemplateRow key={t.id} template={t} />)}
      </div>

      <CreateTemplateDialog open={openCreate} onClose={() => setOpenCreate(false)} />
    </main>
  );
}
