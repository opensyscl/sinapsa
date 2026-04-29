import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { useAuth, type User } from "@/store/auth";

type AuthResponse = { token: string; user: User };

export function useLogin() {
  const setSession = useAuth((s) => s.setSession);
  return useMutation({
    mutationFn: async (input: { email: string; password: string }) => {
      const { data } = await api.post<AuthResponse>("/api/auth/login", input);
      return data;
    },
    onSuccess: (data) => setSession(data.token, data.user),
  });
}

export type RegisterInput = {
  workspace_name: string;
  workspace_slug?: string;
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
};

export function useRegister() {
  const setSession = useAuth((s) => s.setSession);
  return useMutation({
    mutationFn: async (input: RegisterInput) => {
      const { data } = await api.post<AuthResponse>("/api/auth/register", input);
      return data;
    },
    onSuccess: (data) => setSession(data.token, data.user),
  });
}

export function useLogout() {
  const clear = useAuth((s) => s.clear);
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async () => {
      try {
        await api.post("/api/auth/logout");
      } catch {
        // si el token ya estaba inválido seguimos limpiando
      }
    },
    onSettled: () => {
      clear();
      qc.clear();
    },
  });
}

export function useMe(enabled = true) {
  const token = useAuth((s) => s.token);
  return useQuery({
    queryKey: ["auth", "me"],
    enabled: enabled && !!token,
    queryFn: async () => {
      const { data } = await api.get<{ user: User }>("/api/auth/me");
      return data.user;
    },
    staleTime: 60_000,
  });
}
