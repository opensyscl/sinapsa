import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";

export type ApiRequestLog = {
  id: number;
  method: string;
  path: string;
  route_name: string | null;
  status: number;
  duration_ms: number;
  error_code: string | null;
  channel_id: number | null;
  integration_id: number | null;
  caller: string;
  ip: string | null;
  request_body: unknown;
  response_body: unknown;
  created_at: string;
};

export type WebhookLog = {
  id: number;
  source: string;
  channel_id: number | null;
  signature_valid: boolean;
  processed_at: string | null;
  processing_error: string | null;
  payload: unknown;
  created_at: string;
};

type Page<T> = { data: T[]; meta: { current_page: number; last_page: number; total: number } };

export type RequestLogFilters = {
  channel_id?: number;
  integration_id?: number;
  status?: "ok" | "error";
  page?: number;
  per_page?: number;
};

export function useRequestLogs(filters: RequestLogFilters) {
  return useQuery({
    queryKey: ["logs", "requests", filters],
    queryFn: async () =>
      (await api.get<Page<ApiRequestLog>>("/api/v1/logs/requests", { params: filters })).data,
    placeholderData: keepPreviousData,
    refetchInterval: 10_000,
  });
}

export function useWebhookLogs(filters: { channel_id?: number; page?: number }) {
  return useQuery({
    queryKey: ["logs", "webhooks", filters],
    queryFn: async () =>
      (await api.get<Page<WebhookLog>>("/api/v1/logs/webhooks", { params: filters })).data,
    placeholderData: keepPreviousData,
    refetchInterval: 10_000,
  });
}

export type LogSummary = {
  since: string;
  hourly: { hour: string; total: number; errors: number }[];
  totals: { calls: number; errors: number; avg_ms: number | null; webhooks: number };
  by_connection: { channel_id: number | null; integration_id: number | null; calls: number; errors: number; last_at: string }[];
};

export function useLogSummary() {
  return useQuery({
    queryKey: ["logs", "summary"],
    queryFn: async () => (await api.get<LogSummary>("/api/v1/logs/summary")).data,
    refetchInterval: 30_000,
  });
}
