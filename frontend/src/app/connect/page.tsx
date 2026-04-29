"use client";

import { useEffect, useState, useRef, Suspense } from "react";
import { useSearchParams } from "next/navigation";
import { HugeiconsIcon } from "@hugeicons/react";
import {
  CheckmarkCircle01Icon,
  AlertCircleIcon,
  WhatsappIcon,
  Loading03Icon,
} from "@hugeicons/core-free-icons";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { Badge } from "@/components/ui/Badge";

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:48000";

type SessionInfo = {
  session: {
    id: number;
    workspace_id: number;
    allowed_channel_types: string[];
    display_label: string | null;
    return_url: string | null;
    status: string;
    expires_at: string;
  };
  meta: {
    app_id: string;
    graph_version: string;
    wa_embedded_signup_config_id: string;
  };
};

declare global {
  interface Window {
    FB?: {
      init: (cfg: { appId: string; version: string; xfbml?: boolean }) => void;
      login: (
        cb: (response: {
          authResponse?: { code?: string };
          status?: string;
        }) => void,
        opts: { config_id: string; response_type?: string; override_default_response_type?: boolean; extras?: Record<string, unknown> },
      ) => void;
    };
    fbAsyncInit?: () => void;
  }
}

/**
 * Notifica al window.opener (la app del cliente SaaS) y cierra.
 * Si la página fue abierta en mismo tab (no popup), redirige a return_url.
 */
function notifyAndClose(
  payload: { type: "sinapsa:connected" | "sinapsa:cancelled" | "sinapsa:error"; data?: unknown },
  returnUrl: string | null,
) {
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

function ConnectInner() {
  const params = useSearchParams();
  const token = params.get("token");
  const [info, setInfo] = useState<SessionInfo | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [step, setStep] = useState<"idle" | "popping" | "exchanging" | "done">("idle");
  const fbReady = useRef(false);

  useEffect(() => {
    if (!token) {
      setError("Falta el token de sesión.");
      setLoading(false);
      return;
    }
    fetch(`${API_URL}/api/v1/connect-sessions/${token}/info`, {
      headers: { Accept: "application/json" },
    })
      .then(async (r) => {
        if (!r.ok) {
          const e = await r.json().catch(() => ({}));
          throw new Error(e?.error?.message ?? "Sesión inválida o expirada.");
        }
        return r.json() as Promise<SessionInfo>;
      })
      .then((d) => setInfo(d))
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [token]);

  // Carga el FB SDK perezosamente cuando tengamos info y el flujo esté armado
  useEffect(() => {
    if (!info?.meta?.app_id) return;
    if (fbReady.current) return;
    if (document.getElementById("fb-jssdk")) return;

    window.fbAsyncInit = () => {
      window.FB?.init({
        appId: info.meta.app_id,
        version: info.meta.graph_version,
        xfbml: false,
      });
      fbReady.current = true;
    };

    const script = document.createElement("script");
    script.id = "fb-jssdk";
    script.async = true;
    script.defer = true;
    script.crossOrigin = "anonymous";
    script.src = "https://connect.facebook.net/en_US/sdk.js";
    document.body.appendChild(script);
  }, [info]);

  const launchEmbeddedSignup = () => {
    if (!info || !window.FB) {
      setError("FB SDK no disponible.");
      return;
    }
    setStep("popping");

    window.FB.login(
      (response) => {
        if (!response?.authResponse?.code) {
          setStep("idle");
          notifyAndClose({ type: "sinapsa:cancelled" }, info.session.return_url);
          return;
        }

        // Meta también pasa phone_number_id y waba_id como sessionStorage
        // o mediante el param `extras.sessionInfoVersion: 3`. En implementaciones
        // reales se reciben via window.message del popup ES. Para la primera
        // versión asumimos que el FB SDK los inyecta en authResponse.
        const phoneNumberId = (response.authResponse as { phone_number_id?: string })?.phone_number_id ?? "";
        const wabaId = (response.authResponse as { waba_id?: string })?.waba_id ?? "";

        completeConnect(response.authResponse.code, phoneNumberId, wabaId);
      },
      {
        config_id: info.meta.wa_embedded_signup_config_id,
        response_type: "code",
        override_default_response_type: true,
        extras: { sessionInfoVersion: 3 },
      },
    );
  };

  const completeConnect = async (code: string, phoneNumberId: string, wabaId: string) => {
    if (!info || !token) return;
    setStep("exchanging");

    try {
      const r = await fetch(
        `${API_URL}/api/v1/connect-sessions/${token}/complete`,
        {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify({
            channel_type: "whatsapp",
            code,
            phone_number_id: phoneNumberId,
            waba_id: wabaId,
          }),
        },
      );
      const data = await r.json();
      if (!r.ok) {
        throw new Error(data?.error?.message ?? "No se pudo completar la conexión.");
      }
      setStep("done");
      notifyAndClose(
        { type: "sinapsa:connected", data: data.channel },
        info.session.return_url,
      );
    } catch (e) {
      setStep("idle");
      const msg = e instanceof Error ? e.message : "Error completando";
      setError(msg);
      notifyAndClose({ type: "sinapsa:error", data: { message: msg } }, info.session.return_url);
    }
  };

  if (loading) {
    return (
      <Card className="w-full max-w-md p-8 text-center">
        <HugeiconsIcon icon={Loading03Icon} size={28} className="mx-auto animate-spin text-muted-foreground" />
        <p className="mt-4 text-sm text-muted-foreground">Validando sesión…</p>
      </Card>
    );
  }

  if (error || !info) {
    return (
      <Card className="w-full max-w-md p-8 text-center">
        <HugeiconsIcon icon={AlertCircleIcon} size={32} className="mx-auto text-destructive" />
        <h1 className="mt-4 text-lg font-semibold">Sesión no válida</h1>
        <p className="mt-2 text-sm text-muted-foreground">
          {error ?? "El enlace ha expirado o ya fue usado."}
        </p>
        <Button className="mt-6" variant="outline" onClick={() => window.close()}>
          Cerrar ventana
        </Button>
      </Card>
    );
  }

  if (step === "done") {
    return (
      <Card className="w-full max-w-md p-8 text-center">
        <HugeiconsIcon icon={CheckmarkCircle01Icon} size={32} className="mx-auto text-positive" />
        <h1 className="mt-4 text-lg font-semibold">Canal conectado</h1>
        <p className="mt-2 text-sm text-muted-foreground">
          Volviendo a la app…
        </p>
      </Card>
    );
  }

  const metaReady = !!info.meta.app_id && !!info.meta.wa_embedded_signup_config_id;

  return (
    <Card className="w-full max-w-md p-8">
      <div className="flex flex-col items-center gap-3 text-center">
        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-positive/10">
          <HugeiconsIcon icon={WhatsappIcon} size={26} className="text-positive" />
        </div>
        <h1 className="text-xl font-semibold tracking-tight">
          {info.session.display_label ?? "Conecta tu WhatsApp Business"}
        </h1>
        <p className="text-sm text-muted-foreground">
          Pulsa continuar para autorizar el acceso vía Meta. La conexión queda
          vinculada a tu cuenta. No compartiremos credenciales.
        </p>
        <div className="flex flex-wrap justify-center gap-1.5 pt-1">
          {info.session.allowed_channel_types.map((t) => (
            <Badge key={t} tone="outline">
              {t}
            </Badge>
          ))}
        </div>
      </div>

      {!metaReady && (
        <div className="mt-6 rounded-2xl border border-warning/30 bg-warning/5 p-3 text-xs text-warning">
          <strong>Modo dev:</strong> el backend no tiene <code>META_APP_ID</code> /{" "}
          <code>META_WA_EMBEDDED_SIGNUP_CONFIG_ID</code> configurados. El popup
          oficial de Meta no funcionará hasta que pases App Review y configures las
          variables de entorno en el servidor.
        </div>
      )}

      <div className="mt-6 flex flex-col gap-2">
        <Button
          onClick={launchEmbeddedSignup}
          disabled={!metaReady || step !== "idle"}
          className="w-full"
        >
          {step === "popping" && "Esperando ventana de Meta…"}
          {step === "exchanging" && "Conectando…"}
          {step === "idle" && "Continuar con Meta"}
        </Button>
        <Button
          variant="ghost"
          className="w-full"
          onClick={() => notifyAndClose({ type: "sinapsa:cancelled" }, info.session.return_url)}
        >
          Cancelar
        </Button>
      </div>

      <p className="mt-6 text-center text-xs text-muted-foreground">
        Powered by Sinapsa · Tech Provider Meta
      </p>
    </Card>
  );
}

export default function ConnectPage() {
  return (
    <main className="flex min-h-screen items-center justify-center bg-muted/30 px-4 py-10">
      <Suspense fallback={null}>
        <ConnectInner />
      </Suspense>
    </main>
  );
}
