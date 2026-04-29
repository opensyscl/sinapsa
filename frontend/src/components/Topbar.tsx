"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { toast } from "sonner";
import { HugeiconsIcon } from "@hugeicons/react";
import {
  Book01Icon,
  CodeIcon,
  DashboardSquare01Icon,
  DocumentValidationIcon,
  Mailbox01Icon,
  WhatsappIcon,
} from "@hugeicons/core-free-icons";
import { Button } from "@/components/ui/Button";
import { useLogout } from "@/lib/queries/auth";
import { useAuth } from "@/store/auth";
import { cn } from "@/lib/utils";

const NAV = [
  { href: "/dashboard", label: "Dashboard", icon: DashboardSquare01Icon },
  { href: "/inbox", label: "Bandeja", icon: Mailbox01Icon },
  { href: "/canales", label: "Canales", icon: WhatsappIcon },
  { href: "/plantillas", label: "Plantillas", icon: DocumentValidationIcon },
  { href: "/desarrolladores", label: "Devs", icon: CodeIcon },
  { href: "/docs", label: "Docs", icon: Book01Icon },
];

export function Topbar() {
  const router = useRouter();
  const pathname = usePathname();
  const user = useAuth((s) => s.user);
  const logout = useLogout();

  const onLogout = () => {
    logout.mutate(undefined, {
      onSettled: () => {
        toast.success("Sesión cerrada");
        router.replace("/login");
      },
    });
  };

  const initials = (user?.name ?? "?")
    .split(" ")
    .map((p) => p[0])
    .filter(Boolean)
    .slice(0, 2)
    .join("")
    .toUpperCase();

  return (
    <header className="border-b border-border">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
        <div className="flex items-center gap-6">
          <Link href="/dashboard" className="flex items-center gap-2">
            <div className="h-7 w-7 rounded-full bg-foreground" />
            <span className="text-base font-semibold tracking-tight">Sinapsa</span>
          </Link>
          <nav className="hidden md:flex items-center gap-1">
            {NAV.map((item) => {
              const active = pathname === item.href || pathname?.startsWith(item.href + "/");
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={cn(
                    "inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium transition-colors",
                    active
                      ? "bg-foreground text-background"
                      : "text-muted-foreground hover:bg-muted hover:text-foreground",
                  )}
                >
                  <HugeiconsIcon icon={item.icon} size={14} />
                  {item.label}
                </Link>
              );
            })}
          </nav>
        </div>

        <div className="flex items-center gap-3">
          <div className="hidden text-right md:block">
            <div className="text-sm font-medium leading-tight">{user?.name}</div>
            <div className="text-xs text-muted-foreground leading-tight">
              {user?.workspace?.name}
            </div>
          </div>
          <div className="flex h-9 w-9 items-center justify-center rounded-full bg-muted text-xs font-semibold">
            {initials}
          </div>
          <Button
            variant="outline"
            size="sm"
            onClick={onLogout}
            disabled={logout.isPending}
          >
            {logout.isPending ? "Saliendo…" : "Salir"}
          </Button>
        </div>
      </div>
    </header>
  );
}
