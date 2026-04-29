import { HugeiconsIcon } from "@hugeicons/react";
import {
  Tick01Icon,
  TickDouble01Icon,
  AlertCircleIcon,
  Clock01Icon,
} from "@hugeicons/core-free-icons";
import dayjs from "dayjs";
import { cn } from "@/lib/utils";
import type { Message } from "@/lib/queries/messages";

function StatusGlyph({ m }: { m: Message }) {
  if (m.direction !== "outbound") return null;
  switch (m.status) {
    case "queued":
      return <HugeiconsIcon icon={Clock01Icon} size={12} />;
    case "sent":
      return <HugeiconsIcon icon={Tick01Icon} size={12} />;
    case "delivered":
      return <HugeiconsIcon icon={TickDouble01Icon} size={12} />;
    case "read":
      return <HugeiconsIcon icon={TickDouble01Icon} size={12} className="text-positive" />;
    case "failed":
      return <HugeiconsIcon icon={AlertCircleIcon} size={12} className="text-destructive" />;
    default:
      return null;
  }
}

export function MessageBubble({ message }: { message: Message }) {
  const isOutbound = message.direction === "outbound";

  if (message.type === "template") {
    return (
      <div
        className={cn(
          "flex w-full",
          isOutbound ? "justify-end" : "justify-start",
        )}
      >
        <div
          className={cn(
            "max-w-[70%] rounded-3xl border px-4 py-3 text-sm",
            isOutbound
              ? "border-foreground/10 bg-foreground text-background"
              : "border-border bg-muted text-foreground",
          )}
        >
          <div className="text-xs uppercase opacity-70">Plantilla</div>
          <div className="font-medium">{message.template_name}</div>
          {message.body && <div className="mt-1 text-sm opacity-90">{message.body}</div>}
          <div className="mt-1.5 flex items-center gap-1 text-[10px] opacity-70">
            {dayjs(message.created_at).format("HH:mm")}
            <StatusGlyph m={message} />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div
      className={cn(
        "flex w-full",
        isOutbound ? "justify-end" : "justify-start",
      )}
    >
      <div
        className={cn(
          "max-w-[70%] rounded-3xl px-4 py-2 text-sm",
          isOutbound
            ? "bg-foreground text-background"
            : "border border-border bg-muted text-foreground",
        )}
      >
        {message.type === "image" && (
          <div className="mb-1 text-xs opacity-70">📷 Imagen</div>
        )}
        {message.type === "audio" && (
          <div className="mb-1 text-xs opacity-70">🎙️ Audio</div>
        )}
        {message.type === "document" && (
          <div className="mb-1 text-xs opacity-70">📎 Documento</div>
        )}
        <div className="whitespace-pre-wrap break-words">
          {message.body || (message.type === "image" ? "" : "(sin contenido)")}
        </div>
        <div
          className={cn(
            "mt-1 flex items-center justify-end gap-1 text-[10px]",
            isOutbound ? "opacity-70" : "text-muted-foreground",
          )}
        >
          {dayjs(message.created_at).format("HH:mm")}
          <StatusGlyph m={message} />
        </div>
      </div>
    </div>
  );
}
