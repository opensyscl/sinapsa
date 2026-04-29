"use client";

import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "sonner";
import { HugeiconsIcon } from "@hugeicons/react";
import { Copy01Icon, CheckmarkCircle01Icon } from "@hugeicons/core-free-icons";
import {
  Dialog,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/Dialog";
import { Button } from "@/components/ui/Button";
import { FieldError, Input, Label } from "@/components/ui/Input";
import { Badge } from "@/components/ui/Badge";
import { useCreateApiToken } from "@/lib/queries/api-tokens";

const schema = z.object({
  name: z.string().min(2, "Nombre muy corto").max(120),
  scopes: z.array(z.string()).min(1, "Elige al menos un scope"),
  mode: z.enum(["live", "test"]),
});

type FormData = z.output<typeof schema>;

const SCOPE_DEFINITIONS: { value: string; label: string; helper: string }[] = [
  { value: "*", label: "Acceso completo (*)", helper: "Permite todo. Usar con cuidado — solo para integraciones internas." },
  { value: "messages:read", label: "messages:read", helper: "Leer mensajes y conversaciones." },
  { value: "messages:write", label: "messages:write", helper: "Enviar mensajes (texto, plantillas, media)." },
  { value: "conversations:read", label: "conversations:read", helper: "Listar y leer conversaciones." },
  { value: "conversations:write", label: "conversations:write", helper: "Asignar, cerrar, marcar leído." },
  { value: "contacts:read", label: "contacts:read", helper: "Listar y leer contactos." },
  { value: "contacts:write", label: "contacts:write", helper: "Crear, actualizar, borrar contactos (RGPD)." },
  { value: "channels:read", label: "channels:read", helper: "Listar canales conectados y su estado." },
  { value: "templates:read", label: "templates:read", helper: "Listar plantillas WhatsApp." },
  { value: "webhooks:read", label: "webhooks:read", helper: "Listar webhooks salientes (Fase 6)." },
  { value: "webhooks:write", label: "webhooks:write", helper: "Crear/editar webhooks salientes." },
];

export function CreateTokenDialog({
  open,
  onClose,
}: {
  open: boolean;
  onClose: () => void;
}) {
  const create = useCreateApiToken();
  const [plainToken, setPlainToken] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);

  const form = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: "",
      scopes: ["messages:read", "messages:write", "conversations:read"],
      mode: "live",
    },
  });

  useEffect(() => {
    if (!open) {
      form.reset();
      setPlainToken(null);
      setCopied(false);
    }
  }, [open, form]);

  const selectedScopes = form.watch("scopes");

  const toggleScope = (scope: string) => {
    const current = form.getValues("scopes");
    if (current.includes(scope)) {
      form.setValue(
        "scopes",
        current.filter((s) => s !== scope),
        { shouldValidate: true },
      );
    } else {
      form.setValue("scopes", [...current, scope], { shouldValidate: true });
    }
  };

  const onSubmit = (data: FormData) =>
    create.mutate(data, {
      onSuccess: (result) => setPlainToken(result.plain_token),
      onError: (err) => {
        const e = err as { response?: { data?: { message?: string } } };
        toast.error(e.response?.data?.message ?? "Error al crear token");
      },
    });

  const copyToClipboard = async (text: string) => {
    try {
      await navigator.clipboard.writeText(text);
      setCopied(true);
      toast.success("Token copiado al portapapeles");
      setTimeout(() => setCopied(false), 2000);
    } catch {
      toast.error("No se pudo copiar");
    }
  };

  return (
    <Dialog open={open} onClose={onClose} className="max-w-xl">
      {!plainToken ? (
        <>
          <DialogHeader>
            <DialogTitle>Crear API token</DialogTitle>
            <DialogDescription>
              Los tokens permiten a tu CRM, bot o n8n autenticarse contra Sinapsa. El token
              completo se mostrará UNA SOLA VEZ tras crearlo — copia y guárdalo seguro.
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4" noValidate>
            <div className="space-y-2">
              <Label htmlFor="name">Nombre del token</Label>
              <Input
                id="name"
                placeholder="ej. Bot WhatsApp Production"
                {...form.register("name")}
              />
              <FieldError message={form.formState.errors.name?.message} />
            </div>

            <div className="space-y-2">
              <Label>Modo</Label>
              <div className="flex gap-2">
                {(["live", "test"] as const).map((mode) => (
                  <button
                    key={mode}
                    type="button"
                    onClick={() => form.setValue("mode", mode)}
                    className={`flex-1 rounded-2xl border px-4 py-2.5 text-sm font-medium transition-colors ${
                      form.watch("mode") === mode
                        ? "border-foreground bg-foreground text-background"
                        : "border-border bg-background text-muted-foreground hover:bg-muted"
                    }`}
                  >
                    {mode === "live" ? "Live" : "Test"}
                  </button>
                ))}
              </div>
              <p className="text-xs text-muted-foreground">
                Test tokens tienen el mismo poder que live, pero sirven para distinguir
                tráfico de pruebas en logs y métricas.
              </p>
            </div>

            <div className="space-y-2">
              <Label>Scopes</Label>
              <div className="space-y-1.5 rounded-2xl border border-border p-3 max-h-72 overflow-y-auto">
                {SCOPE_DEFINITIONS.map((s) => {
                  const active = selectedScopes.includes(s.value);
                  return (
                    <label
                      key={s.value}
                      className={`flex items-start gap-3 rounded-xl px-2 py-1.5 cursor-pointer hover:bg-muted/60 ${
                        active ? "bg-muted/40" : ""
                      }`}
                    >
                      <input
                        type="checkbox"
                        checked={active}
                        onChange={() => toggleScope(s.value)}
                        className="mt-1"
                      />
                      <div className="min-w-0 flex-1">
                        <div className="text-sm font-medium">
                          <code>{s.label}</code>
                        </div>
                        <div className="text-xs text-muted-foreground">{s.helper}</div>
                      </div>
                    </label>
                  );
                })}
              </div>
              <FieldError message={form.formState.errors.scopes?.message} />
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={onClose}>
                Cancelar
              </Button>
              <Button type="submit" disabled={create.isPending}>
                {create.isPending ? "Creando…" : "Crear token"}
              </Button>
            </DialogFooter>
          </form>
        </>
      ) : (
        <>
          <DialogHeader>
            <div className="flex items-center gap-2">
              <HugeiconsIcon icon={CheckmarkCircle01Icon} size={22} className="text-positive" />
              <DialogTitle>Tu token está listo</DialogTitle>
            </div>
            <DialogDescription>
              Cópialo ahora. <strong>No volverá a mostrarse</strong> — si lo pierdes
              tendrás que crear uno nuevo.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-3">
            <div className="rounded-2xl border border-border bg-muted/40 p-4">
              <code className="block break-all font-mono text-sm">{plainToken}</code>
            </div>
            <Button
              type="button"
              variant={copied ? "secondary" : "primary"}
              className="w-full"
              onClick={() => copyToClipboard(plainToken)}
            >
              <HugeiconsIcon icon={copied ? CheckmarkCircle01Icon : Copy01Icon} size={14} />
              {copied ? "Copiado" : "Copiar al portapapeles"}
            </Button>

            <div className="rounded-2xl border border-border bg-background p-4">
              <p className="text-xs font-semibold text-muted-foreground uppercase mb-2">
                Empieza a usarlo
              </p>
              <pre className="text-xs whitespace-pre-wrap break-all">
{`curl -X POST https://api.sinapsa.app/api/v1/messages \\
  -H "Authorization: Bearer ${plainToken}" \\
  -H "Idempotency-Key: $(uuidgen)" \\
  -H "Content-Type: application/json" \\
  -d '{
    "channel_id": 1,
    "to": { "phone": "+34666123456" },
    "type": "template",
    "template": { "name": "hello_world", "language": "en_US" }
  }'`}
              </pre>
            </div>
          </div>

          <DialogFooter>
            <Button onClick={onClose}>Cerrar</Button>
          </DialogFooter>
        </>
      )}
    </Dialog>
  );
}
