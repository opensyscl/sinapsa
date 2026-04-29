"use client";

import dayjs from "dayjs";
import { HugeiconsIcon } from "@hugeicons/react";
import { Mail01Icon, SmartPhone01Icon, UserIcon } from "@hugeicons/core-free-icons";
import { useConversation } from "@/lib/queries/conversations";
import { Card } from "@/components/ui/Card";
import { Badge } from "@/components/ui/Badge";

export function ContactPanel({ conversationId }: { conversationId: number | null }) {
  const detail = useConversation(conversationId ?? undefined);
  if (!conversationId || !detail.data) {
    return (
      <aside className="hidden h-full w-80 flex-col border-l border-border p-5 lg:flex">
        <p className="text-sm text-muted-foreground">Sin contacto seleccionado.</p>
      </aside>
    );
  }
  const { contact, conversation } = detail.data;

  const initials = (contact?.name ?? contact?.phone ?? "?")
    .split(" ")
    .map((p) => p[0])
    .filter(Boolean)
    .slice(0, 2)
    .join("")
    .toUpperCase();

  return (
    <aside className="hidden h-full w-80 flex-col gap-4 overflow-y-auto border-l border-border bg-background p-5 lg:flex">
      <div className="flex flex-col items-center gap-3 pb-3 text-center">
        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-muted text-xl font-semibold">
          {initials}
        </div>
        <div>
          <h3 className="text-sm font-semibold">{contact.name ?? "(sin nombre)"}</h3>
          <p className="text-xs text-muted-foreground">{contact.phone}</p>
        </div>
        <div className="flex flex-wrap justify-center gap-1.5">
          <Badge tone="outline">via {conversation.channel.display_name}</Badge>
        </div>
      </div>

      <Card className="p-4">
        <h4 className="text-xs font-semibold uppercase text-muted-foreground mb-3">Datos</h4>
        <div className="space-y-2 text-sm">
          {contact.phone && (
            <div className="flex items-center gap-2">
              <HugeiconsIcon icon={SmartPhone01Icon} size={14} className="text-muted-foreground" />
              <a href={`tel:${contact.phone}`} className="hover:underline">
                {contact.phone}
              </a>
            </div>
          )}
          {contact.email && (
            <div className="flex items-center gap-2">
              <HugeiconsIcon icon={Mail01Icon} size={14} className="text-muted-foreground" />
              <a href={`mailto:${contact.email}`} className="hover:underline">
                {contact.email}
              </a>
            </div>
          )}
          {!contact.phone && !contact.email && (
            <p className="text-xs text-muted-foreground">Sin datos de contacto adicionales.</p>
          )}
        </div>
      </Card>

      {contact.identifiers && Object.keys(contact.identifiers).length > 0 && (
        <Card className="p-4">
          <h4 className="text-xs font-semibold uppercase text-muted-foreground mb-3">
            Identidades
          </h4>
          <ul className="space-y-1.5 text-xs">
            {Object.entries(contact.identifiers).map(([k, v]) => (
              <li key={k} className="flex items-center justify-between gap-2">
                <span className="capitalize text-muted-foreground">{k}</span>
                <code className="truncate">{String(v)}</code>
              </li>
            ))}
          </ul>
        </Card>
      )}

      <Card className="p-4">
        <h4 className="text-xs font-semibold uppercase text-muted-foreground mb-3">Conversación</h4>
        <div className="space-y-1.5 text-xs">
          <div className="flex justify-between">
            <span className="text-muted-foreground">Estado</span>
            <Badge tone={conversation.status === "open" ? "positive" : "neutral"}>
              {conversation.status}
            </Badge>
          </div>
          {conversation.last_message_at && (
            <div className="flex justify-between">
              <span className="text-muted-foreground">Último mensaje</span>
              <span>{dayjs(conversation.last_message_at).format("DD/MM HH:mm")}</span>
            </div>
          )}
          {contact.first_seen_at && (
            <div className="flex justify-between">
              <span className="text-muted-foreground">Primera vez</span>
              <span>{dayjs(contact.first_seen_at).format("DD/MM/YYYY")}</span>
            </div>
          )}
          <div className="flex justify-between">
            <span className="text-muted-foreground">Thread</span>
            <code>{conversation.external_thread_id}</code>
          </div>
        </div>
      </Card>

      <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
        <span>
          <HugeiconsIcon icon={UserIcon} size={12} className="inline" /> Contacto #{contact.id}
        </span>
      </div>
    </aside>
  );
}
