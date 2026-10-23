"use client";

import { useEffect, useState, Suspense } from "react";
import { useSearchParams } from "next/navigation";
import { HugeiconsIcon } from "@hugeicons/react";
import { CheckmarkCircle01Icon, AlertCircleIcon, Cancel01Icon } from "@hugeicons/core-free-icons";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { CONNECT_CHANNEL, notifyAndClose, type ConnectResult } from "../notify";

/**
 * Aterrizaje del callback OAuth de Google (`/api/oauth/google/callback` redirige aquí).
 * Le pasamos el resultado a /connect por BroadcastChannel y cerramos. Si nadie
 * responde (popup bloqueado), avisamos directo al opener o vamos a return_url.
 */
function DoneInner() {
  const params = useSearchParams();
  const status = params.get("status") ?? "error";
  const message = params.get("message");
  const [closing, setClosing] = useState(true);

  useEffect(() => {
    const result: ConnectResult =
      status === "connected"
        ? {
            type: "sinapsa:connected",
            data: {
              type: params.get("type"),
              integration_id: Number(params.get("integration_id")),
              email: params.get("email"),
            },
          }
        : status === "cancelled"
          ? { type: "sinapsa:cancelled" }
          : { type: "sinapsa:error", data: { message } };

    // Popup de Google: /connect sigue abierto y es quien habla con la app del cliente.
    // No podemos fiarnos de window.opener ni window.name (Google manda COOP y los borra),
    // así que publicamos el resultado y esperamos el ack de /connect.
    const channel = new BroadcastChannel(CONNECT_CHANNEL);
    let acked = false;
    channel.onmessage = (e: MessageEvent<{ type?: string }>) => {
      if (e.data?.type === "sinapsa:ack") {
        acked = true;
        window.close();
      }
    };
    channel.postMessage(result);

    // Sin /connect escuchando (popup bloqueado, mismo tab): avisamos nosotros
    const fallback = setTimeout(() => {
      if (acked) return;
      notifyAndClose(result, params.get("return_url"));
      setClosing(false);
    }, 600);

    return () => {
      clearTimeout(fallback);
      channel.close();
    };
  }, [params, status, message]);

  const ok = status === "connected";
  const cancelled = status === "cancelled";

  return (
    <Card className="w-full max-w-md p-8 text-center">
      <HugeiconsIcon
        icon={ok ? CheckmarkCircle01Icon : cancelled ? Cancel01Icon : AlertCircleIcon}
        size={32}
        className={ok ? "mx-auto text-positive" : cancelled ? "mx-auto text-muted-foreground" : "mx-auto text-destructive"}
      />
      <h1 className="mt-4 text-lg font-semibold">
        {ok ? "Cuenta conectada" : cancelled ? "Conexión cancelada" : "No se pudo conectar"}
      </h1>
      <p className="mt-2 text-sm text-muted-foreground">
        {ok
          ? params.get("email") ?? "Volviendo a la app…"
          : cancelled
            ? "No se autorizó el acceso."
            : message ?? "Inténtalo de nuevo."}
      </p>
      {!closing && (
        <Button className="mt-6" variant="outline" onClick={() => window.close()}>
          Cerrar ventana
        </Button>
      )}
    </Card>
  );
}

export default function ConnectDonePage() {
  return (
    <main className="flex min-h-screen items-center justify-center bg-muted/30 px-4 py-10">
      <Suspense fallback={null}>
        <DoneInner />
      </Suspense>
    </main>
  );
}
