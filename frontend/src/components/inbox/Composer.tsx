"use client";

import { useState, useRef, useEffect } from "react";
import { toast } from "sonner";
import { HugeiconsIcon } from "@hugeicons/react";
import { Sent02Icon, FileExportIcon } from "@hugeicons/core-free-icons";
import { Button } from "@/components/ui/Button";
import { useSendMessage } from "@/lib/queries/messages";

export function Composer({
  conversationId,
  disabled,
  hint,
}: {
  conversationId: number;
  disabled?: boolean;
  hint?: string;
}) {
  const [body, setBody] = useState("");
  const taRef = useRef<HTMLTextAreaElement>(null);
  const send = useSendMessage(conversationId);

  // Auto-grow del textarea
  useEffect(() => {
    if (!taRef.current) return;
    taRef.current.style.height = "auto";
    taRef.current.style.height = `${Math.min(taRef.current.scrollHeight, 160)}px`;
  }, [body]);

  const submit = () => {
    if (!body.trim() || disabled) return;
    send.mutate(
      { type: "text", body: body.trim() },
      {
        onSuccess: () => setBody(""),
        onError: (err) => {
          const e = err as {
            response?: {
              data?: { error?: { code?: string; message?: string }; message?: string };
            };
          };
          const code = e.response?.data?.error?.code;
          if (code === "outside_24h_window") {
            toast.error(
              "Fuera de la ventana de 24h. Envía una plantilla aprobada en su lugar.",
            );
          } else if (code === "channel_not_connected") {
            toast.error("El canal no está conectado.");
          } else {
            toast.error(
              e.response?.data?.error?.message ??
                e.response?.data?.message ??
                "No se pudo enviar el mensaje",
            );
          }
        },
      },
    );
  };

  const onKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      submit();
    }
  };

  return (
    <div className="border-t border-border bg-background px-4 py-3">
      {hint && (
        <div className="mb-2 rounded-2xl border border-warning/20 bg-warning/5 px-3 py-2 text-xs text-warning">
          {hint}
        </div>
      )}
      <div className="flex items-end gap-2 rounded-3xl border border-border bg-muted/50 px-3 py-2">
        <button
          type="button"
          className="rounded-full p-2 text-muted-foreground hover:bg-muted hover:text-foreground"
          title="Adjuntar (próximamente)"
          disabled
        >
          <HugeiconsIcon icon={FileExportIcon} size={16} />
        </button>
        <textarea
          ref={taRef}
          value={body}
          onChange={(e) => setBody(e.target.value)}
          onKeyDown={onKeyDown}
          placeholder={disabled ? "El canal está desconectado" : "Escribe un mensaje…"}
          rows={1}
          disabled={disabled}
          className="flex-1 resize-none bg-transparent py-1.5 text-sm outline-none placeholder:text-muted-foreground/70 disabled:cursor-not-allowed disabled:opacity-50"
        />
        <Button
          onClick={submit}
          size="icon"
          disabled={disabled || !body.trim() || send.isPending}
          aria-label="Enviar"
        >
          <HugeiconsIcon icon={Sent02Icon} size={16} />
        </Button>
      </div>
      <p className="mt-1.5 text-[11px] text-muted-foreground">
        Pulsa Enter para enviar · Shift + Enter para nueva línea
      </p>
    </div>
  );
}
