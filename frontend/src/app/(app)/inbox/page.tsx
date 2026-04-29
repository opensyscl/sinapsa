"use client";

import { useState } from "react";
import { ConversationList } from "@/components/inbox/ConversationList";
import { MessageThread } from "@/components/inbox/MessageThread";
import { ContactPanel } from "@/components/inbox/ContactPanel";
import { useRealtimeInbox } from "@/lib/queries/realtime-inbox";
import { useAuth } from "@/store/auth";

export default function InboxPage() {
  const workspaceId = useAuth((s) => s.user?.workspace_id ?? null);
  const [selectedId, setSelectedId] = useState<number | null>(null);

  // Suscribe a Reverb en private-workspace.{id}.inbox
  useRealtimeInbox(workspaceId);

  return (
    <main className="mx-auto flex h-[calc(100vh-65px)] w-full max-w-7xl flex-1 overflow-hidden border-x border-border">
      <ConversationList
        selectedId={selectedId}
        onSelect={(c) => setSelectedId(c.id)}
      />
      <MessageThread conversationId={selectedId} />
      <ContactPanel conversationId={selectedId} />
    </main>
  );
}
