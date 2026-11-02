import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";

export type Integration = {
  id: number;
  workspace_id: number;
  provider: "google";
  products: string[];
  external_id: string;
  email: string | null;
  display_name: string | null;
  status: "connected" | "disconnected" | "error";
  is_connected: boolean;
  granted_scopes: string[] | null;
  last_error_code: string | null;
  last_error_message: string | null;
  end_user_id: string | null;
  client_metadata: Record<string, unknown> | null;
  created_at: string;
};

export const integrationKeys = {
  all: ["integrations"] as const,
  list: () => [...integrationKeys.all, "list"] as const,
};

export function useIntegrations() {
  return useQuery({
    queryKey: integrationKeys.list(),
    queryFn: async () => {
      const { data } = await api.get<{ data: Integration[] }>("/api/v1/integrations");
      return data.data;
    },
  });
}

export function useDisconnectIntegration() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/api/v1/integrations/${id}`);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: integrationKeys.all }),
  });
}

/** Abre la Hosted Connect Page de Sinapsa para conectar Google Calendar desde el dashboard. */
export function useConnectGoogleCalendar() {
  return useMutation({
    mutationFn: async () => {
      const { data } = await api.post<{ hosted_url: string }>("/api/v1/connect-sessions", {
        allowed_channel_types: ["google_calendar"],
        display_label: "Sinapsa",
        return_url: `${window.location.origin}/canales?tipo=google_calendar`,
      });
      return data.hosted_url;
    },
    onSuccess: (url) => window.location.assign(url),
  });
}
