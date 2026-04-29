"use client";

import { useEffect } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { getEcho } from "@/lib/echo";
import { conversationKeys } from "./conversations";
import { messageKeys, type Message } from "./messages";
import type { Conversation } from "./conversations";

type MessageReceivedPayload = {
  message: Message;
  conversation: Conversation | null;
};

type MessageStatusUpdatedPayload = {
  message: Message;
};

/**
 * Suscribe el navegador al canal `private-workspace.{id}.inbox` y mantiene
 * cache de TanStack Query coherente cuando llegan eventos de Reverb.
 *
 * - `MessageReceived`: append al thread (si está abierto) + invalida el listado.
 * - `MessageStatusUpdated`: parchea el bubble outbound con el nuevo status.
 *
 * Se llama una sola vez desde el layout autenticado.
 */
export function useRealtimeInbox(workspaceId: number | null | undefined) {
  const qc = useQueryClient();

  useEffect(() => {
    if (!workspaceId) return;
    const echo = getEcho();
    if (!echo) return;

    const channel = echo.private(`workspace.${workspaceId}.inbox`);

    channel.listen(".MessageReceived", (event: MessageReceivedPayload) => {
      // 1) Refresca el listado lateral (re-fetch para que llegue con el shape correcto)
      qc.invalidateQueries({ queryKey: conversationKeys.all });

      // 2) Si tenemos cargado ya el thread de esta conversación, append optimista
      if (event.message?.conversation_id) {
        qc.setQueryData<Message[]>(
          messageKeys.inConversation(event.message.conversation_id),
          (current) => {
            if (!current) return current;
            // Idempotencia: no duplicar si ya está
            if (current.some((m) => m.id === event.message.id)) return current;
            return [...current, event.message];
          },
        );
      }
    });

    channel.listen(".MessageStatusUpdated", (event: MessageStatusUpdatedPayload) => {
      const m = event.message;
      qc.setQueryData<Message[]>(
        messageKeys.inConversation(m.conversation_id),
        (current) => current?.map((x) => (x.id === m.id ? { ...x, ...m } : x)),
      );
    });

    return () => {
      channel.stopListening(".MessageReceived");
      channel.stopListening(".MessageStatusUpdated");
      // No desconectamos el Echo entero — otros componentes pueden estar suscritos.
    };
  }, [workspaceId, qc]);
}
