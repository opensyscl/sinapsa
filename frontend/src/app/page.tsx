import Link from "next/link";
import { Button } from "@/components/ui/Button";
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/ui/Card";

export default function Landing() {
  return (
    <main className="flex flex-1 flex-col">
      <header className="border-b border-border">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
          <div className="flex items-center gap-2">
            <div className="h-7 w-7 rounded-full bg-foreground" />
            <span className="text-lg font-semibold tracking-tight">Sinapsa</span>
          </div>
          <nav className="flex items-center gap-3">
            <Link href="/login">
              <Button variant="ghost" size="sm">
                Entrar
              </Button>
            </Link>
            <Link href="/registro">
              <Button size="sm">Crear cuenta</Button>
            </Link>
          </nav>
        </div>
      </header>

      <section className="mx-auto flex max-w-6xl flex-1 flex-col items-center justify-center gap-10 px-6 py-20 text-center">
        <div className="space-y-5">
          <span className="inline-flex items-center rounded-full border border-border bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
            Pasarela omnicanal · Meta
          </span>
          <h1 className="text-4xl font-semibold tracking-tight md:text-6xl">
            El sistema nervioso central
            <br />
            de tus conversaciones.
          </h1>
          <p className="mx-auto max-w-xl text-base text-muted-foreground md:text-lg">
            Conecta WhatsApp, Instagram y Messenger en una sola API. Bandeja
            unificada, webhooks normalizados, multi-empresa. Sin tocar Meta
            Graph nunca más.
          </p>
          <div className="flex flex-wrap items-center justify-center gap-3 pt-2">
            <Link href="/registro">
              <Button size="lg">Empezar 14 días gratis</Button>
            </Link>
            <Link href="/login">
              <Button size="lg" variant="outline">
                Tengo cuenta
              </Button>
            </Link>
          </div>
        </div>

        <div className="grid w-full grid-cols-1 gap-4 md:grid-cols-3">
          <Card>
            <CardHeader>
              <CardTitle>Una API, tres canales</CardTitle>
              <CardDescription>
                WhatsApp Cloud, Instagram DM y Messenger detrás del mismo
                endpoint. Un solo contrato, payloads normalizados.
              </CardDescription>
            </CardHeader>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>Webhooks fiables</CardTitle>
              <CardDescription>
                Firma HMAC, retries exponenciales, dead-letter, replays
                manuales. Tu CRM nunca se entera del ruido de Meta.
              </CardDescription>
            </CardHeader>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>Multi-empresa</CardTitle>
              <CardDescription>
                Cada workspace con sus canales, sus tokens, su bandeja, su
                facturación. Subdominios y aislamiento por defecto.
              </CardDescription>
            </CardHeader>
          </Card>
        </div>
      </section>

      <footer className="border-t border-border">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-6 text-xs text-muted-foreground">
          <span>© {new Date().getFullYear()} Sinapsa</span>
          <span>v0.1 · pre-MVP</span>
        </div>
      </footer>
    </main>
  );
}
