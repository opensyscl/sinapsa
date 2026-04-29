"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { useAuth } from "@/store/auth";

/**
 * Wrap any protected layout/page. Redirect to /login if no token.
 * SSR-safe: render a placeholder until hydration; persist hydrates client-side.
 */
export function AuthGuard({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const token = useAuth((s) => s.token);
  const hasHydrated = useAuth.persist?.hasHydrated?.() ?? true;

  useEffect(() => {
    if (hasHydrated && !token) {
      router.replace("/login");
    }
  }, [hasHydrated, token, router]);

  if (!hasHydrated) {
    return (
      <div className="flex flex-1 items-center justify-center text-sm text-muted-foreground">
        Cargando…
      </div>
    );
  }

  if (!token) return null;

  return <>{children}</>;
}
