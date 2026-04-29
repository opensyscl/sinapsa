"use client";

import { useEffect, useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "sonner";
import {
  Dialog,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/Dialog";
import { Button } from "@/components/ui/Button";
import { FieldError, Input, Label } from "@/components/ui/Input";
import { Card } from "@/components/ui/Card";
import { useCreateTemplate } from "@/lib/queries/templates";
import { useChannels } from "@/lib/queries/channels";

const LANGUAGES = [
  { code: "es", label: "Español (es)" },
  { code: "es_MX", label: "Español MX (es_MX)" },
  { code: "en_US", label: "English US (en_US)" },
  { code: "en_GB", label: "English UK (en_GB)" },
  { code: "pt_BR", label: "Portugués Brasil (pt_BR)" },
  { code: "fr", label: "Français (fr)" },
];

const CATEGORIES = [
  { code: "UTILITY", label: "Utility — confirmaciones, transacciones, OTP no marketing" },
  { code: "MARKETING", label: "Marketing — promociones (requiere opt-in)" },
  { code: "AUTHENTICATION", label: "Authentication — códigos OTP" },
];

const schema = z.object({
  channel_id: z.coerce.number().int().positive("Selecciona un canal"),
  name: z
    .string()
    .min(1, "Requerido")
    .max(512)
    .regex(/^[a-z0-9_]+$/, "Solo minúsculas, números y guion bajo"),
  language: z.string().min(1),
  category: z.enum(["UTILITY", "MARKETING", "AUTHENTICATION"]),
  body: z.string().min(1, "El cuerpo es obligatorio").max(1024),
  footer: z.string().max(60).optional(),
});

type FormInput = z.input<typeof schema>;
type FormData = z.output<typeof schema>;

/**
 * Dialog para crear plantilla. MVP: solo BODY + FOOTER opcional.
 * Headers (texto/imagen) y botones (quick reply / URL / phone) los dejamos para
 * una iteración posterior — Meta los acepta pero el builder UI es complejo.
 */
export function CreateTemplateDialog({
  open,
  onClose,
}: {
  open: boolean;
  onClose: () => void;
}) {
  const channels = useChannels();
  const create = useCreateTemplate();

  const form = useForm<FormInput, unknown, FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      channel_id: undefined,
      name: "",
      language: "es",
      category: "UTILITY",
      body: "",
      footer: "",
    },
  });

  useEffect(() => {
    if (!open) form.reset();
  }, [open, form]);

  // Detecta variables {{1}}, {{2}}, ... en el body
  const body = form.watch("body");
  const variables = useMemo(() => {
    const matches = body.matchAll(/\{\{(\d+)\}\}/g);
    return [...new Set([...matches].map((m) => m[1]))].sort((a, b) => Number(a) - Number(b));
  }, [body]);

  const onSubmit = (data: FormData) => {
    const components: CreateTemplateInput["components"] = [];

    if (variables.length > 0) {
      components.push({
        type: "BODY",
        text: data.body,
        example: {
          body_text: [variables.map((_, i) => `Ejemplo ${i + 1}`)],
        },
      });
    } else {
      components.push({
        type: "BODY",
        text: data.body,
      });
    }

    if (data.footer) {
      components.push({ type: "FOOTER", text: data.footer });
    }

    create.mutate(
      {
        channel_id: data.channel_id,
        name: data.name,
        language: data.language,
        category: data.category,
        components,
      },
      {
        onSuccess: () => {
          toast.success("Plantilla enviada a Meta para aprobación");
          onClose();
        },
        onError: (err) => {
          const e = err as {
            response?: { data?: { error?: { code?: string; message?: string }; message?: string } };
          };
          toast.error(
            e.response?.data?.error?.message ??
              e.response?.data?.message ??
              "Error al crear plantilla",
          );
        },
      },
    );
  };

  const channelOptions = channels.data?.filter((c) => c.is_connected) ?? [];

  return (
    <Dialog open={open} onClose={onClose} className="max-w-2xl">
      <DialogHeader>
        <DialogTitle>Crear plantilla WhatsApp</DialogTitle>
        <DialogDescription>
          Las plantillas pasan por revisión de Meta. Marketing requiere opt-in del
          contacto. Solo plantillas APPROVED se pueden enviar.
        </DialogDescription>
      </DialogHeader>

      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4" noValidate>
        <div className="grid gap-4 md:grid-cols-2">
          <div className="space-y-2">
            <Label htmlFor="channel_id">Canal</Label>
            <select
              id="channel_id"
              {...form.register("channel_id")}
              className="w-full rounded-2xl border border-border bg-background px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-ring"
            >
              <option value="">Selecciona…</option>
              {channelOptions.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.display_name}
                </option>
              ))}
            </select>
            <FieldError message={form.formState.errors.channel_id?.message} />
          </div>
          <div className="space-y-2">
            <Label htmlFor="language">Idioma</Label>
            <select
              id="language"
              {...form.register("language")}
              className="w-full rounded-2xl border border-border bg-background px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-ring"
            >
              {LANGUAGES.map((l) => (
                <option key={l.code} value={l.code}>
                  {l.label}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div className="space-y-2">
          <Label htmlFor="name">Nombre</Label>
          <Input
            id="name"
            placeholder="bienvenida_arrendatario"
            {...form.register("name")}
          />
          <p className="text-xs text-muted-foreground">
            Identificador único. Solo minúsculas, números y guion bajo. Meta no permite cambiarlo.
          </p>
          <FieldError message={form.formState.errors.name?.message} />
        </div>

        <div className="space-y-2">
          <Label htmlFor="category">Categoría</Label>
          <div className="space-y-1.5">
            {CATEGORIES.map((c) => (
              <label
                key={c.code}
                className="flex items-start gap-3 rounded-2xl border border-border bg-background p-3 cursor-pointer hover:bg-muted/40"
              >
                <input
                  type="radio"
                  value={c.code}
                  {...form.register("category")}
                  className="mt-1"
                />
                <div className="text-sm">
                  <div className="font-medium">
                    <code>{c.code}</code>
                  </div>
                  <div className="text-xs text-muted-foreground">{c.label}</div>
                </div>
              </label>
            ))}
          </div>
        </div>

        <div className="space-y-2">
          <Label htmlFor="body">Cuerpo (BODY)</Label>
          <textarea
            id="body"
            rows={4}
            placeholder="Hola {{1}}, tu visita está confirmada para el {{2}} a las {{3}}."
            {...form.register("body")}
            className="w-full resize-y rounded-2xl border border-border bg-background px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-ring"
          />
          <p className="text-xs text-muted-foreground">
            Usa <code>{"{{1}}"}</code>, <code>{"{{2}}"}</code> … para variables. Detectadas:{" "}
            {variables.length === 0 ? "ninguna" : variables.map((v) => `{{${v}}}`).join(", ")}
          </p>
          <FieldError message={form.formState.errors.body?.message} />
        </div>

        <div className="space-y-2">
          <Label htmlFor="footer">Pie (FOOTER, opcional)</Label>
          <Input
            id="footer"
            placeholder="Sinapsa · Pasarela omnicanal"
            maxLength={60}
            {...form.register("footer")}
          />
          <FieldError message={form.formState.errors.footer?.message} />
        </div>

        {body && (
          <div className="space-y-2">
            <Label>Vista previa</Label>
            <Card className="bg-positive/5 border-positive/20 p-4">
              <p className="whitespace-pre-wrap text-sm">{body}</p>
              {form.watch("footer") && (
                <p className="mt-2 text-xs text-muted-foreground">{form.watch("footer")}</p>
              )}
            </Card>
          </div>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" disabled={create.isPending}>
            {create.isPending ? "Enviando…" : "Enviar a Meta para aprobación"}
          </Button>
        </DialogFooter>
      </form>
    </Dialog>
  );
}

type CreateTemplateInput = import("@/lib/queries/templates").CreateTemplateInput;
