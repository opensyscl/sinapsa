import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";

export type WebhookEndpoint = {
  id: number;
  workspace_id: number;
  url: string;
  description: string | null;
  events: string[];
  status: "active" | "paused" | "failing";
  last_success_at: string | null;
  last_failure_at: string | null;
  consecutive_failures: number;
  created_at: string;
  updated_at: string | null;
};

export type WebhookEndpointListResponse = {
  data: WebhookEndpoint[];
  available_events: string[];
};

export type WebhookDelivery = {
  id: number;
  endpoint_id: number;
  event_id: string;
  event_type: string;
  status: "pending" | "delivered" | "failing" | "dead";
  attempt: number;
  response_status: number | null;
  response_body_preview: string | null;
  error_message: string | null;
  next_attempt_at: string | null;
  delivered_at: string | null;
  failed_at: string | null;
  created_at: string;
  payload?: Record<string, unknown>;
  response_headers?: Record<string, string>;
  response_body?: string;
};

export const webhookKeys = {
  all: ["webhooks"] as const,
  list: () => [...webhookKeys.all, "list"] as const,
  deliveries: (id: number) => [...webhookKeys.all, id, "deliveries"] as const,
};

export function useWebhookEndpoints() {
  return useQuery({
    queryKey: webhookKeys.list(),
    queryFn: async () => {
      const { data } = await api.get<WebhookEndpointListResponse>("/api/v1/webhooks");
      return data;
    },
  });
}

export type CreateWebhookInput = {
  url: string;
  description?: string;
  events: string[];
};

export function useCreateWebhook() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateWebhookInput) => {
      const { data } = await api.post<{
        plain_secret: string;
        webhook: WebhookEndpoint;
      }>("/api/v1/webhooks", input);
      return data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: webhookKeys.all }),
  });
}

export function useUpdateWebhook() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: {
      id: number;
      patch: Partial<Pick<WebhookEndpoint, "url" | "description" | "events" | "status">>;
    }) => {
      const { data } = await api.patch<{ webhook: WebhookEndpoint }>(
        `/api/v1/webhooks/${input.id}`,
        input.patch,
      );
      return data.webhook;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: webhookKeys.all }),
  });
}

export function useDeleteWebhook() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/api/v1/webhooks/${id}`);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: webhookKeys.all }),
  });
}

export function useTestWebhook() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ ok: boolean; delivery: WebhookDelivery }>(
        `/api/v1/webhooks/${id}/test`,
      );
      return data.delivery;
    },
    onSuccess: (_, id) =>
      qc.invalidateQueries({ queryKey: webhookKeys.deliveries(id) }),
  });
}

export function useReplayDelivery() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { endpointId: number; deliveryId: number }) => {
      const { data } = await api.post<{ ok: boolean; delivery: WebhookDelivery }>(
        `/api/v1/webhooks/${input.endpointId}/deliveries/${input.deliveryId}/replay`,
      );
      return data.delivery;
    },
    onSuccess: (_, { endpointId }) =>
      qc.invalidateQueries({ queryKey: webhookKeys.deliveries(endpointId) }),
  });
}

export function useWebhookDeliveries(endpointId: number | undefined) {
  return useQuery({
    queryKey: endpointId ? webhookKeys.deliveries(endpointId) : ["webhooks", "deliveries", "noop"],
    enabled: !!endpointId,
    queryFn: async () => {
      const { data } = await api.get<{ data: WebhookDelivery[] }>(
        `/api/v1/webhooks/${endpointId}/deliveries`,
      );
      return data.data;
    },
    refetchInterval: 5_000, // poll cada 5s mientras esté abierto
  });
}
