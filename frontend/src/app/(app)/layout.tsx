import { AuthGuard } from "@/components/AuthGuard";
import { Topbar } from "@/components/Topbar";

export default function AppLayout({ children }: { children: React.ReactNode }) {
  return (
    <AuthGuard>
      <div className="flex min-h-full flex-1 flex-col">
        <Topbar />
        <div className="flex flex-1 flex-col">{children}</div>
      </div>
    </AuthGuard>
  );
}
