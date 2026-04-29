"use client";

import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "sonner";
import { HugeiconsIcon } from "@hugeicons/react";
import {
  WhatsappIcon,
  InstagramIcon,
  FacebookIcon,
} from "@hugeicons/core-free-icons";
import {
  Dialog,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/Dialog";
import { Button } from "@/components/ui/Button";
import { FieldError, Input, Label } from "@/components/ui/Input";
import {
  useConnectInstagramManual,
  useConnectMessengerManual,
  useConnectWhatsAppManual,
  type Channel,
} from "@/lib/queries/channels";
import { cn } from "@/lib/utils";

type ChannelKind = "whatsapp" | "instagram" | "messenger";

const TABS: { kind: ChannelKind; label: string; icon: typeof WhatsappIcon }[] = [
  { kind: "whatsapp", label: "WhatsApp", icon: WhatsappIcon },
  { kind: "instagram", label: "Instagram", icon: InstagramIcon },
  { kind: "messenger", label: "Messenger", icon: FacebookIcon },
];

export function ConnectChannelDialog({
  open,
  onClose,
}: {
  open: boolean;
  onClose: () => void;
}) {
  const [tab, setTab] = useState<ChannelKind>("whatsapp");

  return (
    <Dialog open={open} onClose={onClose} className="max-w-xl">
      <DialogHeader>
        <DialogTitle>Conectar canal manualmente (dev)</DialogTitle>
        <DialogDescription>
          Esta UI sirve para conectar canales pegando un access_token tuyo. Tus
          clientes SaaS NO la verán — ellos usan{" "}
          <strong>Connect-as-a-Service</strong> con el JS SDK
          (<code>/sdk.js</code>) y el endpoint{" "}
          <code>POST /api/v1/connect-sessions</code>. Mira los docs.
        </DialogDescription>
      </DialogHeader>

      <div className="mb-5 flex items-center gap-1 rounded-2xl border border-border bg-muted p-1">
        {TABS.map((t) => (
          <button
            key={t.kind}
            type="button"
            onClick={() => setTab(t.kind)}
            className={cn(
              "flex flex-1 items-center justify-center gap-2 rounded-xl px-3 py-1.5 text-sm font-medium transition-colors",
              tab === t.kind
                ? "bg-background text-foreground shadow-sm"
                : "text-muted-foreground",
            )}
          >
            <HugeiconsIcon icon={t.icon} size={14} />
            {t.label}
          </button>
        ))}
      </div>

      {tab === "whatsapp" && <WhatsAppForm onClose={onClose} />}
      {tab === "instagram" && <InstagramForm onClose={onClose} />}
      {tab === "messenger" && <MessengerForm onClose={onClose} />}
    </Dialog>
  );
}

function ConnectFormHints({ children }: { children: React.ReactNode }) {
  return (
    <div className="rounded-2xl border border-border bg-muted/40 p-4 text-xs text-muted-foreground">
      {children}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────
// WhatsApp
// ─────────────────────────────────────────────────────────────────────

const waSchema = z.object({
  access_token: z.string().min(20, "Token muy corto"),
  phone_number_id: z.string().min(3, "Requerido"),
  waba_id: z.string().min(3, "Requerido"),
  display_name: z.string().max(120).optional(),
  skip_meta_calls: z.boolean(),
});

function WhatsAppForm({ onClose }: { onClose: () => void }) {
  const connect = useConnectWhatsAppManual();
  const form = useForm<z.output<typeof waSchema>>({
    resolver: zodResolver(waSchema),
    defaultValues: {
      access_token: "",
      phone_number_id: "",
      waba_id: "",
      display_name: "",
      skip_meta_calls: true,
    },
  });

  const onSubmit = (data: z.output<typeof waSchema>) =>
    connect.mutate(data, {
      onSuccess: (channel: Channel) => {
        toast.success(`Canal "${channel.display_name}" conectado`);
        onClose();
      },
      onError: (err) => mapError(err),
    });

  return (
    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4" noValidate>
      <ConnectFormHints>
        Pega el system user access_token desde Business Manager → Configuración → Usuarios del
        sistema. Coge el <code>phone_number_id</code> y <code>WhatsApp Business Account ID</code>{" "}
        desde la consola de WhatsApp.
      </ConnectFormHints>

      <FieldGroup label="Access token" id="wa_access_token" error={form.formState.errors.access_token?.message}>
        <Input id="wa_access_token" type="password" placeholder="EAAJ..." {...form.register("access_token")} />
      </FieldGroup>

      <div className="grid gap-4 md:grid-cols-2">
        <FieldGroup label="phone_number_id" id="wa_pnid" error={form.formState.errors.phone_number_id?.message}>
          <Input id="wa_pnid" {...form.register("phone_number_id")} />
        </FieldGroup>
        <FieldGroup label="waba_id" id="wa_waba" error={form.formState.errors.waba_id?.message}>
          <Input id="wa_waba" {...form.register("waba_id")} />
        </FieldGroup>
      </div>

      <FieldGroup label="Nombre interno (opcional)" id="wa_dn">
        <Input id="wa_dn" placeholder="WhatsApp comercial" {...form.register("display_name")} />
      </FieldGroup>

      <label className="flex items-center gap-2 text-sm">
        <input type="checkbox" {...form.register("skip_meta_calls")} />
        <span>
          Saltar register/subscribe Meta (recomendado en local con tokens de prueba).
        </span>
      </label>

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onClose}>
          Cancelar
        </Button>
        <Button type="submit" disabled={connect.isPending}>
          {connect.isPending ? "Conectando…" : "Conectar WhatsApp"}
        </Button>
      </DialogFooter>
    </form>
  );
}

// ─────────────────────────────────────────────────────────────────────
// Instagram
// ─────────────────────────────────────────────────────────────────────

const igSchema = z.object({
  access_token: z.string().min(20, "Token muy corto"),
  ig_user_id: z.string().min(3, "Requerido"),
  page_id: z.string().optional(),
  display_name: z.string().max(120).optional(),
});

function InstagramForm({ onClose }: { onClose: () => void }) {
  const connect = useConnectInstagramManual();
  const form = useForm<z.output<typeof igSchema>>({
    resolver: zodResolver(igSchema),
    defaultValues: { access_token: "", ig_user_id: "", page_id: "", display_name: "" },
  });

  const onSubmit = (data: z.output<typeof igSchema>) =>
    connect.mutate(data, {
      onSuccess: (channel: Channel) => {
        toast.success(`Canal "${channel.display_name}" conectado`);
        onClose();
      },
      onError: (err) => mapError(err),
    });

  return (
    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4" noValidate>
      <ConnectFormHints>
        Para Instagram DM necesitas un Page Access Token con scopes{" "}
        <code>instagram_basic</code>, <code>instagram_manage_messages</code> y <code>pages_messaging</code>.
        El <code>ig_user_id</code> lo obtienes con <code>GET /{"{page_id}"}?fields=instagram_business_account</code>.
      </ConnectFormHints>

      <FieldGroup label="Access token" id="ig_at" error={form.formState.errors.access_token?.message}>
        <Input id="ig_at" type="password" {...form.register("access_token")} />
      </FieldGroup>

      <div className="grid gap-4 md:grid-cols-2">
        <FieldGroup label="ig_user_id" id="ig_uid" error={form.formState.errors.ig_user_id?.message}>
          <Input id="ig_uid" {...form.register("ig_user_id")} />
        </FieldGroup>
        <FieldGroup label="page_id (opcional)" id="ig_pid">
          <Input id="ig_pid" {...form.register("page_id")} />
        </FieldGroup>
      </div>

      <FieldGroup label="Nombre interno (opcional)" id="ig_dn">
        <Input id="ig_dn" placeholder="Instagram @marca" {...form.register("display_name")} />
      </FieldGroup>

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onClose}>
          Cancelar
        </Button>
        <Button type="submit" disabled={connect.isPending}>
          {connect.isPending ? "Conectando…" : "Conectar Instagram"}
        </Button>
      </DialogFooter>
    </form>
  );
}

// ─────────────────────────────────────────────────────────────────────
// Messenger
// ─────────────────────────────────────────────────────────────────────

const fbSchema = z.object({
  access_token: z.string().min(20, "Token muy corto"),
  page_id: z.string().min(3, "Requerido"),
  display_name: z.string().max(120).optional(),
});

function MessengerForm({ onClose }: { onClose: () => void }) {
  const connect = useConnectMessengerManual();
  const form = useForm<z.output<typeof fbSchema>>({
    resolver: zodResolver(fbSchema),
    defaultValues: { access_token: "", page_id: "", display_name: "" },
  });

  const onSubmit = (data: z.output<typeof fbSchema>) =>
    connect.mutate(data, {
      onSuccess: (channel: Channel) => {
        toast.success(`Canal "${channel.display_name}" conectado`);
        onClose();
      },
      onError: (err) => mapError(err),
    });

  return (
    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4" noValidate>
      <ConnectFormHints>
        Para Messenger pega el Page Access Token (no el user token) con{" "}
        <code>pages_messaging</code> y <code>pages_messaging_subscriptions</code>. El{" "}
        <code>page_id</code> lo encuentras en la URL de tu página de Facebook o en{" "}
        <code>GET /me/accounts</code>.
      </ConnectFormHints>

      <FieldGroup label="Page Access token" id="fb_at" error={form.formState.errors.access_token?.message}>
        <Input id="fb_at" type="password" {...form.register("access_token")} />
      </FieldGroup>

      <FieldGroup label="page_id" id="fb_pid" error={form.formState.errors.page_id?.message}>
        <Input id="fb_pid" {...form.register("page_id")} />
      </FieldGroup>

      <FieldGroup label="Nombre interno (opcional)" id="fb_dn">
        <Input id="fb_dn" placeholder="Página Facebook" {...form.register("display_name")} />
      </FieldGroup>

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onClose}>
          Cancelar
        </Button>
        <Button type="submit" disabled={connect.isPending}>
          {connect.isPending ? "Conectando…" : "Conectar Messenger"}
        </Button>
      </DialogFooter>
    </form>
  );
}

// ─────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────

function FieldGroup({
  label,
  id,
  error,
  children,
}: {
  label: string;
  id: string;
  error?: string;
  children: React.ReactNode;
}) {
  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      {children}
      <FieldError message={error} />
    </div>
  );
}

function mapError(err: unknown) {
  const e = err as {
    response?: { data?: { error?: { message?: string }; message?: string } };
  };
  toast.error(
    e.response?.data?.error?.message ??
      e.response?.data?.message ??
      "No se pudo conectar el canal",
  );
}

// Marca como usado para evitar el TS6133 en `useEffect` no usado fuera del componente.
void useEffect;
