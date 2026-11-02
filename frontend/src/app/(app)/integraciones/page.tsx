"use client";

import Link from "next/link";
import { HugeiconsIcon } from "@hugeicons/react";
import {
  WhatsappIcon,
  InstagramIcon,
  FacebookIcon,
  TelegramIcon,
  TiktokIcon,
  Calendar03Icon,
  MicrosoftIcon,
  ZoomIcon,
  Mail01Icon,
  GoogleSheetIcon,
  GoogleDriveIcon,
  SlackIcon,
  ShopifyIcon,
  StripeIcon,
  Megaphone01Icon,
} from "@hugeicons/core-free-icons";
import { Page, PageHeader } from "@/components/app/Page";
import { useChannels } from "@/lib/queries/channels";
import { useConnectGoogleCalendar, useIntegrations, type Integration } from "@/lib/queries/integrations";
import { Button, buttonVariants } from "@/components/ui/Button";
import { cn } from "@/lib/utils";

/**
 * Catálogo de todo lo que Sinapsa quiere integrar.
 * `available: false` → se muestra en gris como "Próximamente".
 * Para activar una nueva: implementarla en el backend y pasar a `true`.
 */
type CatalogItem = {
  id: string;
  name: string;
  description: string;
  icon: typeof WhatsappIcon;
  available: boolean;
  note?: string;
};

const CATALOG: { category: string; items: CatalogItem[] }[] = [
  {
    category: "Mensajería",
    items: [
      { id: "whatsapp", name: "WhatsApp", description: "WhatsApp Cloud API: mensajes, plantillas y estados.", icon: WhatsappIcon, available: true },
      { id: "instagram", name: "Instagram", description: "DMs de Instagram Business.", icon: InstagramIcon, available: true, note: "Conexión desde el botón pendiente" },
      { id: "messenger", name: "Messenger", description: "Mensajes de páginas de Facebook.", icon: FacebookIcon, available: true, note: "Conexión desde el botón pendiente" },
      { id: "telegram", name: "Telegram", description: "Bots de Telegram.", icon: TelegramIcon, available: false },
      { id: "tiktok", name: "TikTok", description: "Mensajes directos de TikTok Business.", icon: TiktokIcon, available: false },
    ],
  },
  {
    category: "Calendario y reuniones",
    items: [
      { id: "google_calendar", name: "Google Calendar", description: "Disponibilidad y eventos: listar, crear, borrar.", icon: Calendar03Icon, available: true },
      { id: "outlook_calendar", name: "Outlook Calendar", description: "Calendario de Microsoft 365.", icon: MicrosoftIcon, available: false },
      { id: "zoom", name: "Zoom", description: "Crear reuniones al agendar.", icon: ZoomIcon, available: false },
    ],
  },
  {
    category: "Productividad",
    items: [
      { id: "gmail", name: "Gmail", description: "Enviar y leer correos.", icon: Mail01Icon, available: false },
      { id: "google_sheets", name: "Google Sheets", description: "Leer y escribir filas.", icon: GoogleSheetIcon, available: false },
      { id: "google_drive", name: "Google Drive", description: "Archivos y adjuntos.", icon: GoogleDriveIcon, available: false },
      { id: "slack", name: "Slack", description: "Notificaciones a canales.", icon: SlackIcon, available: false },
    ],
  },
  {
    category: "Comercio y publicidad",
    items: [
      { id: "shopify", name: "Shopify", description: "Pedidos y clientes de la tienda.", icon: ShopifyIcon, available: false },
      { id: "stripe", name: "Stripe", description: "Links de pago y estado de cobros.", icon: StripeIcon, available: false },
      { id: "google_ads", name: "Google Ads", description: "Campañas y conversiones.", icon: Megaphone01Icon, available: false },
    ],
  },
];

const integrationId = (i: Integration) =>
  i.products.includes("calendar") ? "google_calendar" : i.provider;

function ConnectGoogleButton() {
  const connect = useConnectGoogleCalendar();
  return (
    <div className="flex flex-col items-end gap-1">
      <Button size="sm" onClick={() => connect.mutate()} disabled={connect.isPending}>
        {connect.isPending ? "Abriendo…" : "Conectar"}
      </Button>
      {connect.isError && <span className="text-xs text-destructive">No se pudo iniciar</span>}
    </div>
  );
}

function CatalogRow({ item, connected }: { item: CatalogItem; connected: number }) {
  return (
    <div className={cn("flex flex-wrap items-center gap-x-3 gap-y-2 py-3.5", !item.available && "text-muted-foreground")}>
      <HugeiconsIcon icon={item.icon} size={18} className={cn("shrink-0", item.available ? "text-foreground" : "opacity-60")} />
      <div className="min-w-0 flex-1 basis-56">
        <p className="text-sm font-medium">{item.name}</p>
        <p className="text-sm text-muted-foreground">
          {item.description}
          {item.note && <span className="text-warning"> · {item.note}</span>}
        </p>
      </div>

      {!item.available ? (
        <span className="text-sm">Próximamente</span>
      ) : (
        <div className="flex items-center gap-3">
          {connected > 0 ? (
            <Link href={`/canales?tipo=${item.id}`} className="text-sm text-positive tabular-nums hover:underline">
              {connected} conectad{connected === 1 ? "a" : "as"}
            </Link>
          ) : (
            <span className="text-sm text-muted-foreground">Sin conectar</span>
          )}
          {item.id === "google_calendar" ? (
            <ConnectGoogleButton />
          ) : (
            <Link href="/canales" className={buttonVariants({ variant: "outline", size: "sm" })}>
              Conectar
            </Link>
          )}
        </div>
      )}
    </div>
  );
}

export default function IntegrationsPage() {
  const channels = useChannels();
  const integrations = useIntegrations();

  // Conexiones activas por id del catálogo
  const counts: Record<string, number> = {};
  for (const c of channels.data ?? []) {
    if (c.status === "connected") counts[c.type] = (counts[c.type] ?? 0) + 1;
  }
  for (const i of integrations.data ?? []) {
    if (i.status === "connected") {
      const id = integrationId(i);
      counts[id] = (counts[id] ?? 0) + 1;
    }
  }

  const all = CATALOG.flatMap((g) => g.items);
  const availableCount = all.filter((i) => i.available).length;

  return (
    <Page className="gap-8">
      <PageHeader
        title="Integraciones"
        subtitle={`${availableCount} de ${all.length} disponibles. Lo que está en gris aún no está integrado.`}
      />

      <div className="grid gap-x-12 gap-y-8 xl:grid-cols-2">
        {CATALOG.map((group) => (
          <section key={group.category}>
            <h2 className="pb-2 text-base font-medium">{group.category}</h2>
            <div className="divide-y divide-rule border-t border-border">
              {[...group.items]
                .sort((a, b) => Number(b.available) - Number(a.available))
                .map((item) => (
                  <CatalogRow key={item.id} item={item} connected={counts[item.id] ?? 0} />
                ))}
            </div>
          </section>
        ))}
      </div>
    </Page>
  );
}
