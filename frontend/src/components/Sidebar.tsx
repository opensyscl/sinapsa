"use client";

import { useState } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { toast } from "sonner";
import { HugeiconsIcon } from "@hugeicons/react";
import {
  Activity01Icon,
  Book01Icon,
  Cancel01Icon,
  CodeIcon,
  DashboardSquare01Icon,
  DocumentValidationIcon,
  LinkSquare02Icon,
  Logout01Icon,
  Mailbox01Icon,
  Menu01Icon,
  PuzzleIcon,
  TestTube01Icon,
  WhatsappIcon,
} from "@hugeicons/core-free-icons";
import { Logo } from "@/components/Logo";
import { useLogout } from "@/lib/queries/auth";
import { useAuth } from "@/store/auth";
import { cn } from "@/lib/utils";

const GROUPS = [
  {
    label: "Operación",
    items: [
      { href: "/dashboard", label: "Inicio", icon: DashboardSquare01Icon },
      { href: "/canales", label: "Conexiones", icon: WhatsappIcon },
      { href: "/integraciones", label: "Integraciones", icon: PuzzleIcon },
      { href: "/inbox", label: "Bandeja", icon: Mailbox01Icon },
      { href: "/plantillas", label: "Plantillas", icon: DocumentValidationIcon },
    ],
  },
  {
    label: "Desarrolladores",
    items: [
      { href: "/registros", label: "Registros", icon: Activity01Icon },
      { href: "/probar-api", label: "Probar API", icon: TestTube01Icon },
      { href: "/desarrolladores", label: "API y webhooks", icon: CodeIcon },
      { href: "/docs", label: "Docs", icon: Book01Icon },
    ],
  },
];

// App de ejemplo (cliente SaaS) que integra Sinapsa: demo/saas-x
const PLAYGROUND_URL = process.env.NEXT_PUBLIC_PLAYGROUND_URL ?? "https://playground.sinapsahq.com";

const ITEMS = GROUPS.flatMap((g) => g.items);

function NavRow({
  href,
  label,
  icon,
  active,
  external,
  onNavigate,
}: {
  href: string;
  label: string;
  icon: typeof Activity01Icon;
  active?: boolean;
  external?: boolean;
  onNavigate?: () => void;
}) {
  const className = cn(
    "flex h-8 items-center gap-2.5 rounded-lg px-2.5 text-sm transition-colors",
    active ? "bg-active font-medium text-foreground" : "text-muted-foreground hover:bg-active/60 hover:text-foreground",
  );
  const content = (
    <>
      <HugeiconsIcon icon={icon} size={16} strokeWidth={1.6} />
      <span className="flex-1">{label}</span>
      {external && <HugeiconsIcon icon={LinkSquare02Icon} size={13} />}
    </>
  );
  return external ? (
    <a href={href} target="_blank" rel="noreferrer" className={className}>
      {content}
    </a>
  ) : (
    <Link href={href} onClick={onNavigate} className={className} aria-current={active ? "page" : undefined}>
      {content}
    </Link>
  );
}

function Nav({ onNavigate }: { onNavigate?: () => void }) {
  const pathname = usePathname();
  const isActive = (href: string) => pathname === href || pathname?.startsWith(href + "/");

  return (
    <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-2">
      {GROUPS.map((group) => (
        <div key={group.label} className="space-y-0.5">
          <p className="px-2.5 pb-1.5 text-xs text-muted-foreground">{group.label}</p>
          {group.items.map((item) => (
            <NavRow key={item.href} {...item} active={isActive(item.href)} onNavigate={onNavigate} />
          ))}
          {group.label === "Desarrolladores" && (
            <NavRow href={PLAYGROUND_URL} label="Playground" icon={TestTube01Icon} external />
          )}
        </div>
      ))}
    </nav>
  );
}

function Account() {
  const router = useRouter();
  const user = useAuth((s) => s.user);
  const logout = useLogout();

  return (
    <div className="flex items-center gap-2.5 px-5 py-4">
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-medium">{user?.name}</p>
        <p className="truncate text-xs text-muted-foreground">{user?.email}</p>
      </div>
      <button
        type="button"
        disabled={logout.isPending}
        aria-label="Cerrar sesión"
        onClick={() =>
          logout.mutate(undefined, {
            onSettled: () => {
              toast.success("Sesión cerrada");
              router.replace("/login");
            },
          })
        }
        className="flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-active hover:text-foreground"
      >
        <HugeiconsIcon icon={Logout01Icon} size={16} />
      </button>
    </div>
  );
}

/** Menú del panel: lateral sobre el marco en escritorio; en móvil, barra de 48px y hoja a pantalla completa. */
export function Sidebar() {
  const [open, setOpen] = useState(false);
  const pathname = usePathname();
  const workspace = useAuth((s) => s.user?.workspace?.name);
  const current = ITEMS.find((i) => pathname === i.href || pathname?.startsWith(i.href + "/"));

  return (
    <>
      <aside className="sticky top-0 hidden h-dvh w-[228px] shrink-0 flex-col md:flex">
        <div className="px-5 pt-5 pb-4">
          <Link href="/dashboard" aria-label="Sinapsa, inicio">
            <Logo />
          </Link>
          <p className="mt-3 truncate text-sm font-medium">{workspace ?? "—"}</p>
        </div>
        <Nav />
        <Account />
      </aside>

      <header className="sticky top-0 z-30 flex h-12 items-center gap-3 bg-frame px-4 md:hidden">
        <button
          type="button"
          aria-label="Abrir menú"
          onClick={() => setOpen(true)}
          className="-ml-1 flex h-11 w-11 items-center justify-center rounded-lg"
        >
          <HugeiconsIcon icon={Menu01Icon} size={20} />
        </button>
        <span className="text-sm font-medium">{current?.label ?? "Sinapsa"}</span>
      </header>

      {open && (
        <div className="fixed inset-0 z-40 flex flex-col bg-frame md:hidden" role="dialog" aria-label="Menú">
          <div className="flex h-12 items-center justify-between px-4">
            <Logo />
            <button
              type="button"
              aria-label="Cerrar menú"
              onClick={() => setOpen(false)}
              className="flex h-11 w-11 items-center justify-center rounded-lg"
            >
              <HugeiconsIcon icon={Cancel01Icon} size={20} />
            </button>
          </div>
          <p className="px-5 pb-2 text-sm font-medium">{workspace ?? "—"}</p>
          <Nav onNavigate={() => setOpen(false)} />
          <Account />
        </div>
      )}
    </>
  );
}
