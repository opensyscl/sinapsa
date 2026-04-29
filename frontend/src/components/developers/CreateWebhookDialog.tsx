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
import { useCreateWebhook } from "@/lib/queries/webhooks";

const schema = z.object({
  url: z.string().url("URL no válida (https://...)"),
  description: z.string().max(255).optional(),
  events: z.array(z.string()).min(1, "Elige al menos un evento"),
});

type FormData = z.output<typeof schema>;

const EVENT_DEFINITIONS: { value: string; label: string; helper: string }[] = [
  { value: "*", label: "Todos los eventos (*)", helper: "Recibe absolutamente todo. Útil cuando aún no sabes qué necesitas." },
  { value: "message.*", label: "message.*", helper: "Toda la familia de eventos de mensaje." },
  { value: "message.received", label: "message.received", helper: "Mensaje entrante de un contacto." },
  { value: "message.sent", label: "message.sent", helper: "Mensaje saliente aceptado por Meta." },
  { value: "message.delivered", label: "message.delivered", helper: "Confirmación de entrega del operador." },
  { value: "message.read", label: "message.read", helper: "El destinatario lo ha leído." },
  { value: "message.failed", label: "message.failed", helper: "Meta rechazó el envío." },
];

export function CreateWebhookDialog({
  open,
  onClose,
}: {
  open: boolean;
  onClose: () => void;
}) {
  const create = useCreateWebhook();
  const [plainSecret, setPlainSecret] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);

  const form = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      url: "",
      description: "",
      events: ["message.received", "message.sent"],
    },
  });

  useEffect(() => {
    if (!open) {
      form.reset();
      setPlainSecret(null);
      setCopied(false);
    }
  }, [open, form]);

  const selectedEvents = form.watch("events");

  const toggleEvent = (event: string) => {
    const current = form.getValues("events");
    if (current.includes(event)) {
      form.setValue(
        "events",
        current.filter((e) => e !== event),
        { shouldValidate: true },
      );
    } else {
      form.setValue("events", [...current, event], { shouldValidate: true });
    }
  };

  const onSubmit = (data: FormData) =>
    create.mutate(data, {
      onSuccess: (result) => setPlainSecret(result.plain_secret),
      onError: (err) => {
        const e = err as { response?: { data?: { message?: string } } };
        toast.error(e.response?.data?.message ?? "Error al crear webhook");
      },
    });

  const copyToClipboard = async (text: string) => {
    try {
      await navigator.clipboard.writeText(text);
      setCopied(true);
      toast.success("Secret copiado");
      setTimeout(() => setCopied(false), 2000);
    } catch {
      toast.error("No se pudo copiar");
    }
  };

  return (
    <Dialog open={open} onClose={onClose} className="max-w-xl">
      {!plainSecret ? (
        <>
          <DialogHeader>
            <DialogTitle>Suscribir webhook</DialogTitle>
            <DialogDescription>
              Sinapsa enviará un POST firmado HMAC a tu URL cada vez que ocurra un
              evento del workspace. El secret se mostrará UNA VEZ tras crear.
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4" noValidate>
            <div className="space-y-2">
              <Label htmlFor="url">URL</Label>
              <Input
                id="url"
                type="url"
                placeholder="https://crm.ejemplo.com/webhooks/sinapsa"
                {...form.register("url")}
              />
              <FieldError message={form.formState.errors.url?.message} />
            </div>

            <div className="space-y-2">
              <Label htmlFor="description">Descripción (opcional)</Label>
              <Input
                id="description"
                placeholder="Producción CRM Acme"
                {...form.register("description")}
              />
            </div>

            <div className="space-y-2">
              <Label>Eventos</Label>
              <div className="space-y-1.5 rounded-2xl border border-border p-3 max-h-72 overflow-y-auto">
                {EVENT_DEFINITIONS.map((s) => {
                  const active = selectedEvents.includes(s.value);
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
                        onChange={() => toggleEvent(s.value)}
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
              <FieldError message={form.formState.errors.events?.message} />
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={onClose}>
                Cancelar
              </Button>
              <Button type="submit" disabled={create.isPending}>
                {create.isPending ? "Creando…" : "Crear webhook"}
              </Button>
            </DialogFooter>
          </form>
        </>
      ) : (
        <>
          <DialogHeader>
            <div className="flex items-center gap-2">
              <HugeiconsIcon icon={CheckmarkCircle01Icon} size={22} className="text-positive" />
              <DialogTitle>Webhook suscrito</DialogTitle>
            </div>
            <DialogDescription>
              Cópialo y guárdalo seguro. <strong>No volverá a mostrarse</strong>.
              Tu app debe usarlo para validar la firma <code>X-Sinapsa-Signature</code>{" "}
              de cada delivery.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-3">
            <div className="rounded-2xl border border-border bg-muted/40 p-4">
              <code className="block break-all font-mono text-sm">{plainSecret}</code>
            </div>
            <Button
              type="button"
              variant={copied ? "secondary" : "primary"}
              className="w-full"
              onClick={() => copyToClipboard(plainSecret)}
            >
              <HugeiconsIcon icon={copied ? CheckmarkCircle01Icon : Copy01Icon} size={14} />
              {copied ? "Copiado" : "Copiar secret"}
            </Button>

            <div className="rounded-2xl border border-border bg-background p-4">
              <p className="text-xs font-semibold text-muted-foreground uppercase mb-2">
                Verificación de firma (Node)
              </p>
              <pre className="text-xs whitespace-pre-wrap break-all">
{`import crypto from "node:crypto";

function verify(req, secret) {
  const header = req.headers["x-sinapsa-signature"];
  const [tPart, v1Part] = header.split(",");
  const t = tPart.split("=")[1];
  const v1 = v1Part.split("=")[1];

  const expected = crypto
    .createHmac("sha256", secret)
    .update(\`\${t}.\${req.rawBody}\`)
    .digest("hex");

  if (!crypto.timingSafeEqual(Buffer.from(v1), Buffer.from(expected))) {
    throw new Error("Bad signature");
  }
  if (Math.abs(Date.now() / 1000 - Number(t)) > 300) {
    throw new Error("Stale timestamp (>5 min)");
  }
}`}
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
