import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";

export type Message = {
  id: number;
  conversation_id: number;
  channel_id: number;
  contact_id: number;
  direction: "inbound" | "outbound";
  status: "queued" | "sent" | "delivered" | "read" | "failed";
  type: string;
  external_id: string | null;
  body: string | null;
  media_url: string | null;
  media_mime: string | null;
  template_name: string | null;
  template_payload: { language?: string; components?: unknown[] } | null;
  error_code: string | null;
  error_message: string | null;
  sent_at: string | null;
  delivered_at: string | null;
  read_at: string | null;
  failed_at: string | null;
  created_at: string;
};

export const messageKeys = {
  all: ["messages"] as const,
  inConversation: (conversationId: number) =>
    [...messageKeys.all, "conversation", conversationId] as const,
};

export function useConversationMessages(conversationId: number | undefined) {
  return useQuery({
    queryKey: conversationId
      ? messageKeys.inConversation(conversationId)
      : ["messages", "noop"],
    enabled: !!conversationId,
    queryFn: async () => {
      const { data } = await api.get<{ data: Message[] }>(
        `/api/v1/conversations/${conversationId}/messages`,
      );
      // El backend devuelve DESC; el cliente pinta cronológico.
      return [...data.data].reverse();
    },
    staleTime: 5_000,
  });
}

export type SendMessageInput =
  | {
      type: "text";
      body: string;
    }
  | {
      type: "template";
      template_name: string;
      template_language: string;
      template_components?: unknown[];
    };

export function useSendMessage(conversationId: number | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: SendMessageInput) => {
      if (!conversationId) throw new Error("No conversation selected");
      const { data } = await api.post<{ message: Message }>(
        `/api/v1/conversations/${conversationId}/messages`,
        input,
      );
      return data.message;
    },
    onSuccess: () => {
      if (!conversationId) return;
      qc.invalidateQueries({ queryKey: messageKeys.inConversation(conversationId) });
      qc.invalidateQueries({ queryKey: ["conversations"] });
    },
  });
}
