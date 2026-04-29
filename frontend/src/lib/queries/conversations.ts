import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";

export type ConversationContact = {
  id: number;
  name: string | null;
  phone: string | null;
  email: string | null;
  avatar_url: string | null;
};

export type ConversationChannel = {
  id: number;
  type: string;
  display_name: string;
  status: string;
};

export type ConversationLastMessage = {
  id: number;
  direction: "inbound" | "outbound";
  type: string;
  status: string;
  preview: string;
  created_at: string;
};

export type Conversation = {
  id: number;
  workspace_id: number;
  channel_id: number;
  contact_id: number;
  external_thread_id: string;
  status: "open" | "pending" | "closed" | "snoozed";
  assigned_to_user_id: number | null;
  last_message_at: string | null;
  unread_count: number;
  metadata: Record<string, unknown> | null;
  channel: ConversationChannel;
  contact: ConversationContact;
  assigned_to: { id: number; name: string; email: string; role: string } | null;
  last_message: ConversationLastMessage | null;
  created_at: string;
};

export type ConversationDetail = Conversation;

export type Contact = ConversationContact & {
  workspace_id: number;
  identifiers: Record<string, string> | null;
  attributes: Record<string, unknown> | null;
  opt_ins: Record<string, unknown> | null;
  first_seen_at: string | null;
  last_seen_at: string | null;
  created_at: string;
};

export const conversationKeys = {
  all: ["conversations"] as const,
  list: (filters: Record<string, unknown> = {}) =>
    [...conversationKeys.all, "list", filters] as const,
  detail: (id: number) => [...conversationKeys.all, "detail", id] as const,
};

export type ConversationFilters = {
  status?: string;
  channel_id?: number;
  assigned_to_user_id?: number;
  q?: string;
};

export function useConversations(filters: ConversationFilters = {}) {
  return useQuery({
    queryKey: conversationKeys.list(filters),
    queryFn: async () => {
      const { data } = await api.get<{ data: Conversation[] }>(
        "/api/v1/conversations",
        { params: filters },
      );
      return data.data;
    },
    staleTime: 10_000,
  });
}

export function useConversation(id: number | undefined) {
  return useQuery({
    queryKey: id ? conversationKeys.detail(id) : ["conversations", "detail", "noop"],
    enabled: !!id,
    queryFn: async () => {
      const { data } = await api.get<{
        conversation: ConversationDetail;
        contact: Contact;
      }>(`/api/v1/conversations/${id}`);
      return data;
    },
  });
}

export function useUpdateConversation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: {
      id: number;
      patch: Partial<Pick<Conversation, "status" | "assigned_to_user_id">>;
    }) => {
      const { data } = await api.patch<{ conversation: Conversation }>(
        `/api/v1/conversations/${input.id}`,
        input.patch,
      );
      return data.conversation;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: conversationKeys.all }),
  });
}

export function useMarkRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.post(`/api/v1/conversations/${id}/read`);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: conversationKeys.all }),
  });
}
