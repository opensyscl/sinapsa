import Link from "next/link";
import { Logo } from "@/components/Logo";

export const CONTACT_EMAIL = "opensyscl@gmail.com";
export const LEGAL_UPDATED = "1 de noviembre de 2026";

/** Contenedor común de las páginas legales (privacidad, términos, borrado de datos). */
export function LegalPage({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-1 flex-col">
      <header className="border-b border-border">
        <div className="mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
          <Link href="/" aria-label="Sinapsa">
            <Logo />
          </Link>
          <nav className="flex gap-4 text-sm text-muted-foreground">
            <Link href="/privacy" className="hover:text-foreground">Privacidad</Link>
            <Link href="/terms" className="hover:text-foreground">Términos</Link>
            <Link href="/data-deletion" className="hover:text-foreground">Borrado de datos</Link>
          </nav>
        </div>
      </header>
      <main className="mx-auto w-full max-w-3xl flex-1 px-6 py-14">
        <h1 className="text-4xl font-medium tracking-tight">{title}</h1>
        <p className="mt-2 text-sm text-muted-foreground">Última actualización: {LEGAL_UPDATED}</p>
        <div className="mt-10 space-y-8 text-[15px] leading-relaxed text-foreground/90 [&_h2]:mb-3 [&_h2]:text-xl [&_h2]:font-medium [&_h2]:tracking-tight [&_li]:ml-5 [&_li]:list-disc [&_ul]:space-y-1.5 [&_p+p]:mt-3 [&_a]:underline [&_a]:underline-offset-4">
          {children}
        </div>
      </main>
      <footer className="border-t border-border">
        <div className="mx-auto max-w-3xl px-6 py-6 text-xs text-muted-foreground">
          © {new Date().getFullYear()} Sinapsa · <a href={`mailto:${CONTACT_EMAIL}`}>{CONTACT_EMAIL}</a>
        </div>
      </footer>
    </div>
  );
}
