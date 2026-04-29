import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";

export type Channel = {
  id: number;
  workspace_id: number;
  type: "whatsapp" | "instagram" | "messenger";
  display_name: string;
  external_id: string;
  meta_business_id: string | null;
  status: "pending" | "connected" | "disconnected" | "error";
  is_connected: boolean;
  token_expires_at: string | null;
  webhook_subscribed_at: string | null;
  last_health_check_at: string | null;
  last_error_code: string | null;
  last_error_message: string | null;
  config: Record<string, unknown> | null;
  templates_count?: number;
  created_at: string;
};

export type WaTemplate = {
  id: number;
  channel_id: number;
  name: string;
  language: string;
  category: string | null;
  status: "PENDING" | "APPROVED" | "REJECTED" | "DISABLED" | "PAUSED";
  components: unknown[] | null;
  meta_template_id: string | null;
  last_synced_at: string | null;
  rejected_reason: string | null;
};

export const channelKeys = {
  all: ["channels"] as const,
  list: () => [...channelKeys.all, "list"] as const,
  detail: (id: number) => [...channelKeys.all, "detail", id] as const,
  templates: (id: number) => [...channelKeys.all, id, "templates"] as const,
};

export function useChannels() {
  return useQuery({
    queryKey: channelKeys.list(),
    queryFn: async () => {
      const { data } = await api.get<{ data: Channel[] }>("/api/v1/channels");
      return data.data;
    },
  });
}

export function useChannel(id: number | undefined) {
  return useQuery({
    queryKey: id ? channelKeys.detail(id) : ["channels", "detail", "noop"],
    enabled: !!id,
    queryFn: async () => {
      const { data } = await api.get<{ channel: Channel }>(`/api/v1/channels/${id}`);
      return data.channel;
    },
  });
}

export type ConnectWhatsAppManualInput = {
  access_token: string;
  phone_number_id: string;
  waba_id: string;
  display_name?: string;
  skip_meta_calls?: boolean;
};

export function useConnectWhatsAppManual() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: ConnectWhatsAppManualInput) => {
      const { data } = await api.post<{ channel: Channel }>(
        "/api/v1/channels/whatsapp/connect-manual",
        input,
      );
      return data.channel;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: channelKeys.all }),
  });
}

export type ConnectInstagramManualInput = {
  access_token: string;
  ig_user_id: string;
  page_id?: string;
  display_name?: string;
};

export function useConnectInstagramManual() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: ConnectInstagramManualInput) => {
      const { data } = await api.post<{ channel: Channel }>(
        "/api/v1/channels/instagram/connect-manual",
        input,
      );
      return data.channel;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: channelKeys.all }),
  });
}

export type ConnectMessengerManualInput = {
  access_token: string;
  page_id: string;
  display_name?: string;
};

export function useConnectMessengerManual() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: ConnectMessengerManualInput) => {
      const { data } = await api.post<{ channel: Channel }>(
        "/api/v1/channels/messenger/connect-manual",
        input,
      );
      return data.channel;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: channelKeys.all }),
  });
}

export type ConnectWhatsAppEmbeddedInput = {
  code: string;
  phone_number_id: string;
  waba_id: string;
  display_name?: string;
};

export function useConnectWhatsAppEmbedded() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: ConnectWhatsAppEmbeddedInput) => {
      const { data } = await api.post<{ channel: Channel }>(
        "/api/v1/channels/whatsapp/connect",
        input,
      );
      return data.channel;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: channelKeys.all }),
  });
}

export function useDisconnectChannel() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.post(`/api/v1/channels/${id}/disconnect`);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: channelKeys.all }),
  });
}

export function useChannelTemplates(channelId: number | undefined) {
  return useQuery({
    queryKey: channelId ? channelKeys.templates(channelId) : ["templates", "noop"],
    enabled: !!channelId,
    queryFn: async () => {
      const { data } = await api.get<{ data: WaTemplate[] }>(
        `/api/v1/channels/${channelId}/templates`,
      );
      return data.data;
    },
  });
}

export function useSyncTemplates() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (channelId: number) => {
      const { data } = await api.post<{ synced: number }>(
        `/api/v1/channels/${channelId}/templates/sync`,
      );
      return data.synced;
    },
    onSuccess: (_, channelId) => {
      qc.invalidateQueries({ queryKey: channelKeys.templates(channelId) });
      qc.invalidateQueries({ queryKey: channelKeys.list() });
    },
  });
}

export function useChannelHealthCheck() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (channelId: number) => {
      const { data } = await api.post<{ ok: boolean; channel: Channel }>(
        `/api/v1/channels/${channelId}/health-check`,
      );
      return data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: channelKeys.all }),
  });
}
