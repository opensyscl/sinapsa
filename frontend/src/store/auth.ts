import { create } from "zustand";
import { persist } from "zustand/middleware";

export type Workspace = {
  id: number;
  slug: string;
  name: string;
  status: string;
  plan_code: string;
  billing_cycle: string | null;
  trial_ends_at: string | null;
  is_trialing: boolean;
  trial_days_left: number;
  logo_url: string | null;
  contact_email: string | null;
};

export type User = {
  id: number;
  workspace_id: number | null;
  name: string;
  email: string;
  role: string;
  last_login_at: string | null;
  is_super_admin: boolean;
  workspace?: Workspace | null;
};

type AuthState = {
  token: string | null;
  user: User | null;
  setSession: (token: string, user: User) => void;
  setUser: (user: User) => void;
  clear: () => void;
};

export const useAuth = create<AuthState>()(
  persist(
    (set) => ({
      token: null,
      user: null,
      setSession: (token, user) => set({ token, user }),
      setUser: (user) => set({ user }),
      clear: () => set({ token: null, user: null }),
    }),
    { name: "sinapsa-auth" },
  ),
);
