import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";

export type WaTemplate = {
  id: number;
  channel_id: number;
  name: string;
  language: string;
  category: string | null;
  status: "PENDING" | "APPROVED" | "REJECTED" | "DISABLED" | "PAUSED";
  components: Array<{
    type: "BODY" | "HEADER" | "FOOTER" | "BUTTONS";
    text?: string;
    format?: string;
    example?: { body_text?: string[][] };
    buttons?: Array<{ type: string; text: string; url?: string; phone_number?: string }>;
  }> | null;
  meta_template_id: string | null;
  last_synced_at: string | null;
  rejected_reason: string | null;
};

export type TemplatesListResponse = {
  data: WaTemplate[];
  available_categories: string[];
  available_statuses: string[];
};

export const templateKeys = {
  all: ["templates"] as const,
  list: (filters: Record<string, unknown> = {}) =>
    [...templateKeys.all, "list", filters] as const,
  detail: (id: number) => [...templateKeys.all, "detail", id] as const,
};

export type TemplateFilters = {
  channel_id?: number;
  status?: string;
  language?: string;
  q?: string;
};

export function useTemplates(filters: TemplateFilters = {}) {
  return useQuery({
    queryKey: templateKeys.list(filters),
    queryFn: async () => {
      const { data } = await api.get<TemplatesListResponse>("/api/v1/templates", {
        params: filters,
      });
      return data;
    },
  });
}

export type CreateTemplateInput = {
  channel_id: number;
  name: string;
  language: string;
  category: "UTILITY" | "MARKETING" | "AUTHENTICATION";
  components: Array<{
    type: "BODY" | "HEADER" | "FOOTER" | "BUTTONS";
    text?: string;
    format?: string;
    example?: { body_text?: string[][] };
    buttons?: unknown[];
  }>;
};

export function useCreateTemplate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateTemplateInput) => {
      const { data } = await api.post<{ template: WaTemplate }>(
        "/api/v1/templates",
        input,
      );
      return data.template;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: templateKeys.all }),
  });
}

export function useDeleteTemplate() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/api/v1/templates/${id}`);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: templateKeys.all }),
  });
}

export function useSyncChannelTemplates() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (channelId: number) => {
      const { data } = await api.post<{ synced: number }>("/api/v1/templates/sync", {
        channel_id: channelId,
      });
      return data.synced;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: templateKeys.all }),
  });
}
