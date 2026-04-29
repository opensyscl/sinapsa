import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";

export type ApiToken = {
  id: number;
  workspace_id: number;
  name: string;
  prefix: string;
  scopes: string[];
  mode: "live" | "test";
  last_used_at: string | null;
  last_used_ip: string | null;
  expires_at: string | null;
  revoked_at: string | null;
  is_revoked: boolean;
  created_at: string;
  created_by?: { id: number; name: string; email: string };
};

export type ApiTokenListResponse = {
  data: ApiToken[];
  available_scopes: string[];
};

export const apiTokenKeys = {
  all: ["api-tokens"] as const,
  list: () => [...apiTokenKeys.all, "list"] as const,
};

export function useApiTokens() {
  return useQuery({
    queryKey: apiTokenKeys.list(),
    queryFn: async () => {
      const { data } = await api.get<ApiTokenListResponse>("/api/v1/api-tokens");
      return data;
    },
  });
}

export type CreateApiTokenInput = {
  name: string;
  scopes: string[];
  mode?: "live" | "test";
  expires_at?: string;
};

export function useCreateApiToken() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateApiTokenInput) => {
      const { data } = await api.post<{ plain_token: string; token: ApiToken }>(
        "/api/v1/api-tokens",
        input,
      );
      return data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: apiTokenKeys.all }),
  });
}

export function useRevokeApiToken() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/api/v1/api-tokens/${id}`);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: apiTokenKeys.all }),
  });
}
