import { AuthGuard } from "@/components/AuthGuard";
import { Sidebar } from "@/components/Sidebar";

/**
 * Shell del panel ("framed sheet"): el menú vive en el marco tintado y cada
 * pantalla en una hoja blanca inset 8px a la derecha, arriba y abajo.
 */
export default function AppLayout({ children }: { children: React.ReactNode }) {
  return (
    <AuthGuard>
      <div className="flex min-h-dvh flex-1 flex-col bg-frame md:flex-row">
        <Sidebar />
        <div className="flex min-w-0 flex-1 flex-col bg-background md:my-2 md:mr-2 md:rounded-2xl md:border md:border-border">
          {children}
        </div>
      </div>
    </AuthGuard>
  );
}
