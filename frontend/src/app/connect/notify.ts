export const CONNECT_CHANNEL = "sinapsa-connect";

export type ConnectResult = {
  type: "sinapsa:connected" | "sinapsa:cancelled" | "sinapsa:error";
  data?: unknown;
};

/**
 * Notifica al window.opener (la app del cliente SaaS) y cierra.
 * Si la página fue abierta en mismo tab (no popup), redirige a return_url.
 */
export function notifyAndClose(payload: ConnectResult, returnUrl: string | null) {
  if (window.opener && !window.opener.closed) {
    try {
      window.opener.postMessage(payload, "*");
    } catch {
      // noop
    }
    setTimeout(() => window.close(), 300);
    return;
  }
  if (returnUrl) {
    const u = new URL(returnUrl);
    u.searchParams.set("sinapsa_status", payload.type.replace("sinapsa:", ""));
    window.location.replace(u.toString());
  }
}
