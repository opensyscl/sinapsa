"use client";

import Echo from "laravel-echo";
import Pusher from "pusher-js";
import { useAuth } from "@/store/auth";

declare global {
  // eslint-disable-next-line no-var
  var Pusher: typeof import("pusher-js");
}

let echoInstance: Echo<"reverb"> | null = null;

/**
 * Devuelve la instancia singleton de Echo conectada a Reverb (WebSocket).
 *
 * - Bearer token leído del Zustand store (sinapsa-auth en localStorage).
 * - El authEndpoint es `/api/broadcasting/auth` (montado en bootstrap/app.php
 *   con middleware api+sanctum).
 *
 * Si el token cambia (login / logout), `resetEcho()` debe llamarse para reconectar.
 */
export function getEcho(): Echo<"reverb"> | null {
  if (typeof window === "undefined") return null;
  if (echoInstance) return echoInstance;

  const token = useAuth.getState().token;
  if (!token) return null;

  // pusher-js global (lo exige laravel-echo internamente).
  (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;

  const apiUrl =
    process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:48000";

  echoInstance = new Echo({
    broadcaster: "reverb",
    key: process.env.NEXT_PUBLIC_REVERB_KEY ?? "sinapsa-key-local",
    wsHost: process.env.NEXT_PUBLIC_REVERB_HOST ?? "localhost",
    wsPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 48080),
    wssPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 48080),
    forceTLS: process.env.NEXT_PUBLIC_REVERB_SCHEME === "https",
    enabledTransports: ["ws", "wss"],
    authEndpoint: `${apiUrl}/api/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
      },
    },
  });

  return echoInstance;
}

export function resetEcho(): void {
  if (echoInstance) {
    try {
      (echoInstance as unknown as { disconnect: () => void }).disconnect();
    } catch {
      // noop
    }
    echoInstance = null;
  }
}
