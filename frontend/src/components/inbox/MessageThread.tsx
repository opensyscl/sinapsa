"use client";

import { useEffect, useRef } from "react";
import dayjs from "dayjs";
import { HugeiconsIcon } from "@hugeicons/react";
import { Mailbox01Icon } from "@hugeicons/core-free-icons";
import { useConversation, useMarkRead } from "@/lib/queries/conversations";
import { useConversationMessages } from "@/lib/queries/messages";
import { MessageBubble } from "./MessageBubble";
import { Composer } from "./Composer";
import { Badge } from "@/components/ui/Badge";

export function MessageThread({ conversationId }: { conversationId: number | null }) {
  const detail = useConversation(conversationId ?? undefined);
  const messages = useConversationMessages(conversationId ?? undefined);
  const markRead = useMarkRead();
  const scrollRef = useRef<HTMLDivElement>(null);

  // Scroll al fondo cuando cambia la conversación o llega mensaje nuevo
  useEffect(() => {
    if (!scrollRef.current) return;
    scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
  }, [conversationId, messages.data?.length]);

  // Marcar leído al abrir si tiene unread
  useEffect(() => {
    const conv = detail.data?.conversation;
    if (conv && conv.unread_count > 0) {
      markRead.mutate(conv.id);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [conversationId, detail.data?.conversation?.id]);

  if (!conversationId) {
    return (
      <div className="flex h-full flex-1 flex-col items-center justify-center gap-3 px-6 text-center text-muted-foreground">
        <HugeiconsIcon icon={Mailbox01Icon} size={36} className="opacity-40" />
        <p className="text-sm">Selecciona una conversación para ver el hilo.</p>
      </div>
    );
  }

  const conv = detail.data?.conversation;
  const channelDisconnected = conv?.channel?.status !== "connected";

  // Ventana 24h WA: si el último inbound es >24h, no se puede texto libre
  const lastInbound = (conv?.metadata as { last_inbound_at?: string } | null)?.last_inbound_at;
  const outside24h =
    !lastInbound || dayjs().diff(dayjs(lastInbound), "hour") >= 24;

  const composerHint = channelDisconnected
    ? "El canal está desconectado. Reconecta en /canales para volver a enviar."
    : outside24h
    ? "Fuera de la ventana 24h. Envía una plantilla aprobada para reabrir conversación."
    : undefined;

  return (
    <section className="flex h-full flex-1 flex-col">
      <header className="flex items-center justify-between gap-3 border-b border-border px-5 py-3">
        <div className="min-w-0">
          <h2 className="truncate text-sm font-semibold tracking-tight">
            {conv?.contact?.name ?? conv?.contact?.phone ?? "Conversación"}
          </h2>
          <p className="truncate text-xs text-muted-foreground">
            {conv?.contact?.phone}
            {conv?.channel && (
              <>
                {" · "}via {conv.channel.display_name}
              </>
            )}
          </p>
        </div>
        <div className="flex items-center gap-2">
          {conv?.status && (
            <Badge tone={conv.status === "open" ? "positive" : "neutral"}>{conv.status}</Badge>
          )}
        </div>
      </header>

      <div ref={scrollRef} className="flex-1 overflow-y-auto bg-background">
        <div className="mx-auto flex max-w-3xl flex-col gap-2 px-5 py-6">
          {messages.isLoading && (
            <div className="text-center text-xs text-muted-foreground">Cargando mensajes…</div>
          )}
          {messages.data?.length === 0 && (
            <div className="text-center text-xs text-muted-foreground">
              Sin mensajes todavía en esta conversación.
            </div>
          )}
          {messages.data?.map((m) => <MessageBubble key={m.id} message={m} />)}
        </div>
      </div>

      <Composer
        conversationId={conversationId}
        disabled={channelDisconnected}
        hint={composerHint}
      />
    </section>
  );
}
