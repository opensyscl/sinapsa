"use client";

import { HugeiconsIcon } from "@hugeicons/react";
import { Search01Icon, WhatsappIcon } from "@hugeicons/core-free-icons";
import dayjs from "dayjs";
import { useState } from "react";
import { cn } from "@/lib/utils";
import { useConversations, type Conversation } from "@/lib/queries/conversations";
import { Input } from "@/components/ui/Input";

const channelDot = {
  whatsapp: "bg-positive",
  instagram: "bg-accent-foreground",
  messenger: "bg-foreground",
} as const;

export function ConversationList({
  selectedId,
  onSelect,
}: {
  selectedId: number | null;
  onSelect: (c: Conversation) => void;
}) {
  const [q, setQ] = useState("");
  const conversations = useConversations({ q: q.trim() || undefined, status: "open" });

  return (
    <aside className="flex h-full w-full flex-col border-r border-border md:w-80 lg:w-96">
      <div className="border-b border-border p-4">
        <div className="relative">
          <HugeiconsIcon
            icon={Search01Icon}
            size={14}
            className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"
          />
          <Input
            placeholder="Buscar contacto, teléfono…"
            className="pl-9"
            value={q}
            onChange={(e) => setQ(e.target.value)}
          />
        </div>
      </div>

      <div className="flex-1 overflow-y-auto">
        {conversations.isLoading && (
          <div className="p-6 text-sm text-muted-foreground">Cargando…</div>
        )}
        {conversations.data?.length === 0 && (
          <div className="flex h-full flex-col items-center justify-center px-6 py-12 text-center text-sm text-muted-foreground">
            <HugeiconsIcon icon={WhatsappIcon} size={28} className="mb-3 opacity-50" />
            <p>Sin conversaciones todavía.</p>
            <p className="text-xs mt-1">Cuando llegue un mensaje, aparecerá aquí.</p>
          </div>
        )}
        <ul>
          {conversations.data?.map((c) => {
            const active = c.id === selectedId;
            const initials =
              (c.contact?.name ?? c.contact?.phone ?? "?")
                .split(" ")
                .map((p) => p[0])
                .filter(Boolean)
                .slice(0, 2)
                .join("")
                .toUpperCase();

            return (
              <li key={c.id}>
                <button
                  type="button"
                  onClick={() => onSelect(c)}
                  className={cn(
                    "flex w-full items-start gap-3 border-b border-border/60 px-4 py-3 text-left transition-colors",
                    active ? "bg-muted" : "hover:bg-muted/60",
                  )}
                >
                  <div className="relative shrink-0">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                      {initials}
                    </div>
                    <div
                      className={cn(
                        "absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full ring-2 ring-background",
                        channelDot[c.channel.type as keyof typeof channelDot] ?? "bg-foreground",
                      )}
                      title={c.channel.display_name}
                    />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-baseline justify-between gap-2">
                      <p className="truncate text-sm font-medium">
                        {c.contact?.name ?? c.contact?.phone ?? "(sin nombre)"}
                      </p>
                      {c.last_message_at && (
                        <span className="shrink-0 text-[11px] text-muted-foreground">
                          {dayjs(c.last_message_at).format("HH:mm")}
                        </span>
                      )}
                    </div>
                    <div className="flex items-baseline justify-between gap-2 mt-0.5">
                      <p className="truncate text-xs text-muted-foreground">
                        {c.last_message?.preview ?? "—"}
                      </p>
                      {c.unread_count > 0 && (
                        <span className="shrink-0 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-foreground px-1.5 text-[11px] font-medium text-background">
                          {c.unread_count}
                        </span>
                      )}
                    </div>
                  </div>
                </button>
              </li>
            );
          })}
        </ul>
      </div>
    </aside>
  );
}
