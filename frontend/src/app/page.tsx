import Link from "next/link";
import { HugeiconsIcon } from "@hugeicons/react";
import {
  ApiIcon,
  ArrowRight01Icon,
  Calendar03Icon,
  ConnectIcon,
  DocumentValidationIcon,
  FacebookIcon,
  InstagramIcon,
  Mailbox01Icon,
  PlusSignIcon,
  Shield01Icon,
  Tick02Icon,
  WebhookIcon,
  WhatsappIcon,
} from "@hugeicons/core-free-icons";
import { Logo } from "@/components/Logo";
import { AsciiTriangle } from "@/components/landing/AsciiTriangle";
import { cn } from "@/lib/utils";

/* ───────────────────────── datos ───────────────────────── */

const NAV = [
  { href: "#producto", label: "Producto" },
  { href: "#como-funciona", label: "Cómo funciona" },
  { href: "#desarrolladores", label: "Desarrolladores" },
  { href: "#precios", label: "Precios" },
  { href: "#faq", label: "FAQ" },
];

const CHANNELS = [
  { name: "WhatsApp", icon: WhatsappIcon },
  { name: "Instagram", icon: InstagramIcon },
  { name: "Messenger", icon: FacebookIcon },
  { name: "Google Calendar", icon: Calendar03Icon },
];

const COMPARISON = {
  rows: ["Aprobación de Meta", "Tokens de acceso", "Webhooks", "Canales", "Tiempo hasta producción", "Mantenimiento"],
  columns: [
    {
      title: "Integrar Meta tú mismo",
      values: ["Tu propia App Review", "Los guardas y renuevas tú", "Uno por canal, sin reintentos", "Tres APIs distintas", "Semanas o meses", "Cada cambio de Graph API"],
    },
    {
      title: "Sinapsa",
      highlight: true,
      values: ["Usas la nuestra", "Cifrados y renovados solos", "Uno solo, firmado y con reintentos", "Una API para todos", "Una tarde", "Lo hacemos nosotros"],
    },
    {
      title: "Herramientas de chat",
      values: ["Incluida", "No los ves", "Limitados o de pago", "Atados a su bandeja", "Días", "Dependes de su roadmap"],
    },
  ],
};

const FEATURES = [
  { icon: ApiIcon, title: "Una API para todo", text: "Envía y recibe por WhatsApp, Instagram y Messenger con el mismo endpoint y el mismo formato de mensaje." },
  { icon: WebhookIcon, title: "Webhooks fiables", text: "Un webhook normalizado, firmado con HMAC, con reintentos, cola de fallidos y reenvío manual." },
  { icon: ConnectIcon, title: "Conexión en un clic", text: "Tus usuarios conectan su número o su página desde tu app con nuestro botón. Tú no tocas Meta." },
  { icon: DocumentValidationIcon, title: "Plantillas de WhatsApp", text: "Crea, envía a aprobación y sincroniza plantillas sin salir de la API." },
  { icon: Mailbox01Icon, title: "Bandeja en tiempo real", text: "Todas las conversaciones de cada cliente en una bandeja con actualizaciones al instante." },
  { icon: Calendar03Icon, title: "Agenda con Google Calendar", text: "Consulta disponibilidad y crea citas en el calendario de tus usuarios desde la misma API." },
];

const STEPS = [
  { n: "01", title: "Crea tu cuenta y tu token", text: "Regístrate, genera una clave sk_ con los permisos justos y apunta tus webhooks." },
  { n: "02", title: "Tus usuarios conectan su canal", text: "Agrega el botón de Sinapsa a tu app. Cada usuario conecta su WhatsApp, Instagram o Google en un popup oficial." },
  { n: "03", title: "Envía y recibe", text: "Mandas mensajes con un POST y recibes todo en tu webhook, ya normalizado." },
];

const BENEFITS = [
  "Sin App Review propia ni verificación de Meta.",
  "Separación por cliente y por usuario final desde el primer día.",
  "Tokens cifrados en reposo y renovación automática.",
  "Idempotencia en cada envío: nunca un mensaje duplicado.",
];

const FAQ = [
  { q: "¿Necesito mi propia app de Meta?", a: "No. Sinapsa es Tech Provider de Meta: tus usuarios conectan sus cuentas a través de nuestra app, así que no tienes que pasar App Review ni manejar tokens." },
  { q: "¿Qué canales soporta?", a: "WhatsApp Cloud API, Instagram Direct y Facebook Messenger para mensajería, y Google Calendar para agenda. Telegram y otros vienen después." },
  { q: "¿Cómo se separan los datos de cada cliente?", a: "Cada empresa tiene su propio espacio aislado, y dentro de él cada conexión queda asociada al usuario final que la hizo. Puedes filtrar todo por ese identificador." },
  { q: "¿Dónde quedan los tokens de acceso?", a: "Cifrados en reposo en nuestra base de datos. Nunca viajan a tu frontend ni a tus logs, y los renovamos antes de que venzan." },
  { q: "¿Cómo cobran?", a: "Sin suscripción. Pagas por uso, un margen por mensaje sobre la tarifa oficial de Meta. Escríbenos y te pasamos los números para tu volumen." },
];

/* ───────────────────────── piezas ───────────────────────── */

function Tag({ children, dark, onAccent }: { children: React.ReactNode; dark?: boolean; onAccent?: boolean }) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-3 py-1 text-xs font-medium",
        dark ? "bg-white/10 text-white/80" : onAccent ? "bg-background text-foreground" : "bg-accent text-accent-foreground",
      )}
    >
      {children}
    </span>
  );
}

function CtaLink({
  href,
  children,
  variant = "dark",
  className,
}: {
  href: string;
  children: React.ReactNode;
  variant?: "dark" | "light" | "outline";
  className?: string;
}) {
  return (
    <Link
      href={href}
      className={cn(
        "group inline-flex h-12 items-center gap-3 rounded-full pl-6 pr-1.5 text-sm font-medium transition-colors",
        variant === "dark" && "bg-foreground text-background hover:bg-foreground/90",
        variant === "light" && "bg-background text-foreground hover:bg-background/90",
        variant === "outline" && "border border-border bg-background text-foreground hover:bg-muted",
        className,
      )}
    >
      {children}
      <span
        className={cn(
          "flex h-9 w-9 items-center justify-center rounded-full transition-transform group-hover:translate-x-0.5",
          variant === "dark" ? "bg-background text-foreground" : "bg-foreground text-background",
        )}
      >
        <HugeiconsIcon icon={ArrowRight01Icon} size={16} />
      </span>
    </Link>
  );
}

function SectionHead({
  tag,
  title,
  text,
  center,
  onAccent,
}: {
  tag: string;
  title: React.ReactNode;
  text?: string;
  center?: boolean;
  onAccent?: boolean;
}) {
  return (
    <div className={cn("space-y-4", center && "mx-auto max-w-2xl text-center")}>
      <Tag onAccent={onAccent}>{tag}</Tag>
      <h2 className="text-4xl font-medium tracking-tight md:text-5xl">{title}</h2>
      {text && <p className="text-base text-muted-foreground md:text-lg">{text}</p>}
    </div>
  );
}

// Patrón de puntos del hero (SVG, sin degradados)
function DotPattern() {
  return (
    <svg aria-hidden className="pointer-events-none absolute inset-0 h-full w-full text-white/[0.07]">
      <defs>
        <pattern id="dots" width="22" height="22" patternUnits="userSpaceOnUse">
          <circle cx="2" cy="2" r="1.4" fill="currentColor" />
        </pattern>
      </defs>
      <rect width="100%" height="100%" fill="url(#dots)" />
    </svg>
  );
}

// Mock de conversación: lo que recibe tu webhook
function ChatMock() {
  return (
    <div className="w-full space-y-3 rounded-3xl border border-border bg-background p-5">
      <div className="flex items-center gap-3 border-b border-border pb-4">
        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted">
          <HugeiconsIcon icon={WhatsappIcon} size={18} />
        </div>
        <div className="flex-1">
          <div className="text-sm font-medium">Camila Rojas</div>
          <div className="text-xs text-muted-foreground">WhatsApp · +56 9 •••• 4821</div>
        </div>
        <span className="rounded-full bg-positive/10 px-2.5 py-0.5 text-xs font-medium text-positive">En línea</span>
      </div>
      <div className="max-w-[80%] rounded-2xl rounded-tl-md bg-muted px-4 py-2.5 text-sm">
        Hola, ¿tienen hora para mañana en la tarde?
      </div>
      <div className="ml-auto max-w-[80%] rounded-2xl rounded-tr-md bg-foreground px-4 py-2.5 text-sm text-background">
        ¡Hola Camila! Tenemos 16:00 o 17:30. ¿Cuál te acomoda?
      </div>
      <div className="max-w-[80%] rounded-2xl rounded-tl-md bg-muted px-4 py-2.5 text-sm">17:30 perfecto, gracias</div>
      <div className="flex items-center gap-2 rounded-2xl bg-accent px-4 py-2.5 text-xs">
        <HugeiconsIcon icon={Calendar03Icon} size={14} />
        Cita creada en Google Calendar · mañana 17:30
      </div>
    </div>
  );
}

function CodeBlock({ title, code }: { title: string; code: string }) {
  return (
    <div className="overflow-hidden rounded-3xl bg-foreground text-background">
      <div className="flex items-center gap-2 border-b border-white/10 px-5 py-3">
        <span className="h-2.5 w-2.5 rounded-full bg-white/20" />
        <span className="h-2.5 w-2.5 rounded-full bg-white/20" />
        <span className="h-2.5 w-2.5 rounded-full bg-white/20" />
        <span className="ml-2 text-xs text-white/60">{title}</span>
      </div>
      <pre className="overflow-x-auto p-5 font-mono text-[13px] leading-relaxed text-white/90">{code}</pre>
    </div>
  );
}

const SEND_CODE = `curl -X POST https://api.sinapsahq.com/api/v1/messages \\
  -H "Authorization: Bearer sk_live_..." \\
  -H "Idempotency-Key: 7f1c2e" \\
  -H "Content-Type: application/json" \\
  -d '{
    "channel_id": 12,
    "to": { "phone": "+56912344821" },
    "type": "text",
    "text": { "body": "¡Hola Camila! Tenemos 16:00 o 17:30." }
  }'`;

const WEBHOOK_CODE = `{
  "id": "evt_01JBX4...",
  "type": "message.received",
  "occurred_at": "2026-11-01T18:42:10-03:00",
  "data": {
    "channel": { "id": 12, "type": "whatsapp" },
    "contact": { "name": "Camila Rojas", "phone": "+56912344821" },
    "message": {
      "direction": "inbound",
      "type": "text",
      "body": "17:30 perfecto, gracias"
    }
  }
}`;

/* ───────────────────────── página ───────────────────────── */

export default function Landing() {
  return (
    <div className="flex flex-1 flex-col">
      {/* Header */}
      <header className="sticky top-0 z-40 border-b border-border bg-background/90 backdrop-blur">
        <div className="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-4">
          <Link href="/" aria-label="Sinapsa">
            <Logo />
          </Link>
          <nav className="hidden items-center gap-1 lg:flex">
            {NAV.map((item) => (
              <a
                key={item.href}
                href={item.href}
                className="rounded-full px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
              >
                {item.label}
              </a>
            ))}
          </nav>
          <div className="flex items-center gap-2">
            <Link href="/login" className="rounded-full px-4 py-2 text-sm font-medium hover:bg-muted">
              Entrar
            </Link>
            <Link
              href="/registro"
              className="rounded-full bg-foreground px-4 py-2 text-sm font-medium text-background hover:bg-foreground/90"
            >
              Crear cuenta
            </Link>
          </div>
        </div>
      </header>

      <main className="flex flex-1 flex-col gap-28 pb-5 md:gap-36">
        {/* Hero */}
        <section className="px-3 pt-3 md:px-5 md:pt-5">
          <div className="relative overflow-hidden rounded-[40px] bg-foreground text-background">
            <DotPattern />
            <div className="relative mx-auto flex max-w-4xl flex-col items-center gap-8 px-6 pb-16 pt-24 text-center md:pt-32">
              <Tag dark>Tech Provider de Meta</Tag>
              <h1 className="text-5xl font-medium leading-[1.05] tracking-tight md:text-7xl">
                Todas tus conversaciones,
                <br />
                una sola API.
              </h1>
              <p className="max-w-2xl text-base text-white/70 md:text-lg">
                Conecta WhatsApp, Instagram y Messenger de tus clientes a tu producto sin crear una app de Meta,
                sin App Review y sin manejar tokens.
              </p>
              <div className="flex flex-wrap items-center justify-center gap-3">
                <CtaLink href="/registro" variant="light">
                  Empezar gratis
                </CtaLink>
                <a
                  href="#como-funciona"
                  className="inline-flex h-12 items-center rounded-full border border-white/20 px-6 text-sm font-medium text-white hover:bg-white/10"
                >
                  Ver cómo funciona
                </a>
              </div>
            </div>

            <AsciiTriangle className="pointer-events-none relative -mt-4 block h-64 w-full md:h-96" />

            <div className="relative px-6 pb-10">
              <div className="mx-auto mb-6 flex max-w-xs items-center gap-4 text-sm text-white/60">
                <span className="h-px flex-1 bg-white/20" />
                Conecta
                <span className="h-px flex-1 bg-white/20" />
              </div>
              <div className="mx-auto grid max-w-5xl grid-cols-2 gap-3 md:grid-cols-4">
                {CHANNELS.map((c) => (
                  <div
                    key={c.name}
                    className="flex h-20 items-center justify-center gap-3 rounded-3xl bg-white/[0.06] text-base font-medium"
                  >
                    <HugeiconsIcon icon={c.icon} size={22} />
                    {c.name}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </section>

        {/* Comparación */}
        <section id="producto" className="mx-auto w-full max-w-7xl scroll-mt-24 px-6">
          <SectionHead
            center
            tag="Comparación"
            title="Sinapsa vs. hacerlo a mano"
            text="Integrar Meta bien toma meses. Con Sinapsa lo tienes funcionando en una tarde."
          />
          {/* Móvil: una tarjeta por opción, Sinapsa primero */}
          <div className="mt-12 space-y-3 md:hidden">
            {[...COMPARISON.columns]
              .sort((a, b) => Number(!!b.highlight) - Number(!!a.highlight))
              .map((col) => (
                <div
                  key={col.title}
                  className={cn("rounded-3xl border p-5", col.highlight ? "border-foreground bg-accent" : "border-border")}
                >
                  <div className="mb-3 text-sm font-semibold">{col.highlight ? <Logo height={24} /> : col.title}</div>
                  <dl className="space-y-2 text-sm">
                    {COMPARISON.rows.map((r, i) => (
                      <div key={r} className="flex justify-between gap-4">
                        <dt className="text-muted-foreground">{r}</dt>
                        <dd className="text-right font-medium">{col.values[i]}</dd>
                      </div>
                    ))}
                  </dl>
                </div>
              ))}
          </div>

          <div className="mt-14 hidden md:block">
            <div className="grid grid-cols-[1.1fr_1fr_1fr_1fr] gap-3">
              <div className="pt-16">
                {COMPARISON.rows.map((r) => (
                  <div key={r} className="flex h-12 items-center text-sm font-medium">
                    {r}
                  </div>
                ))}
              </div>
              {COMPARISON.columns.map((col) => (
                <div
                  key={col.title}
                  className={cn(
                    "rounded-3xl border px-5 pb-5",
                    col.highlight ? "border-foreground bg-accent" : "border-border",
                  )}
                >
                  <div className="flex h-16 items-center justify-center text-center text-sm font-semibold">
                    {col.highlight ? <Logo height={24} /> : col.title}
                  </div>
                  {col.values.map((v) => (
                    <div
                      key={v}
                      className={cn(
                        "flex h-12 items-center justify-center gap-2 text-center text-sm",
                        col.highlight ? "text-foreground" : "text-muted-foreground",
                      )}
                    >
                      {col.highlight && <HugeiconsIcon icon={Tick02Icon} size={14} className="shrink-0" />}
                      {v}
                    </div>
                  ))}
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* Features */}
        <section className="px-3 md:px-5">
          <div className="rounded-[40px] bg-accent px-6 py-20 md:px-14">
            <div className="mx-auto max-w-7xl">
              <SectionHead
                onAccent
                tag="Producto"
                title={
                  <>
                    Todo lo que necesitas para hablar
                    <br className="hidden md:block" /> con tus clientes.
                  </>
                }
                text="Nosotros nos encargamos de Meta, de los tokens y de los reintentos. Tú te enfocas en tu producto."
              />
              <div className="mt-14 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {FEATURES.map((f) => (
                  <div key={f.title} className="flex flex-col gap-10 rounded-3xl bg-background p-7">
                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-foreground text-background">
                      <HugeiconsIcon icon={f.icon} size={22} />
                    </div>
                    <div className="space-y-2">
                      <h3 className="text-xl font-medium tracking-tight">{f.title}</h3>
                      <p className="text-sm leading-relaxed text-muted-foreground">{f.text}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </section>

        {/* Pasos */}
        <section id="como-funciona" className="mx-auto w-full max-w-7xl scroll-mt-24 px-6">
          <SectionHead
            center
            tag="Cómo funciona"
            title="En producción en 3 pasos"
            text="De cero a recibir mensajes de tus clientes, sin hablar con Meta."
          />
          <div className="mt-14 grid gap-4 md:grid-cols-3">
            {STEPS.map((s, i) => (
              <div
                key={s.n}
                className={cn(
                  "flex min-h-80 flex-col justify-between rounded-3xl p-7",
                  i === 1 ? "bg-foreground text-background" : "bg-muted",
                )}
              >
                <span className={cn("font-mono text-sm", i === 1 ? "text-white/60" : "text-muted-foreground")}>
                  {s.n}
                </span>
                <div className="space-y-2">
                  <h3 className="text-2xl font-medium tracking-tight">{s.title}</h3>
                  <p className={cn("text-sm leading-relaxed", i === 1 ? "text-white/70" : "text-muted-foreground")}>
                    {s.text}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </section>

        {/* Split: beneficios */}
        <section className="mx-auto grid w-full max-w-7xl items-center gap-12 px-6 lg:grid-cols-2 lg:gap-20">
          <div className="rounded-[40px] bg-accent p-6 md:p-12">
            <ChatMock />
          </div>
          <div className="space-y-8">
            <SectionHead
              tag="Por qué Sinapsa"
              title="Hecho para SaaS que hablan con sus clientes."
              text="CRMs, agendas, bots y plataformas que necesitan WhatsApp para cada uno de sus clientes, sin volverse expertos en Meta."
            />
            <ul className="space-y-2.5">
              {BENEFITS.map((b) => (
                <li key={b} className="flex items-center gap-3 rounded-2xl bg-muted px-4 py-3 text-sm">
                  <HugeiconsIcon icon={Shield01Icon} size={16} className="shrink-0" />
                  {b}
                </li>
              ))}
            </ul>
            <CtaLink href="/registro">Crear cuenta</CtaLink>
          </div>
        </section>

        {/* Desarrolladores */}
        <section id="desarrolladores" className="mx-auto w-full max-w-7xl scroll-mt-24 px-6">
          <SectionHead
            tag="Desarrolladores"
            title="Un POST para enviar. Un webhook para recibir."
            text="Mismo formato para todos los canales, errores claros y claves con permisos acotados."
          />
          <div className="mt-14 grid gap-4 lg:grid-cols-2">
            <CodeBlock title="Enviar un mensaje" code={SEND_CODE} />
            <CodeBlock title="Lo que llega a tu webhook" code={WEBHOOK_CODE} />
          </div>
        </section>

        {/* Precios */}
        <section id="precios" className="scroll-mt-24 px-3 md:px-5">
          <div className="rounded-[40px] bg-accent px-6 py-20 md:px-14">
            <div className="mx-auto max-w-5xl">
              <SectionHead
                center
                onAccent
                tag="Precios"
                title="Sin suscripción. Pagas por uso."
                text="Un margen por mensaje sobre la tarifa oficial de Meta. Sin mínimos ni permanencia."
              />
              <div className="mt-14 grid gap-4 md:grid-cols-2">
                <div className="flex flex-col gap-8 rounded-3xl bg-background p-8">
                  <div className="space-y-2">
                    <h3 className="text-xl font-medium">Para empezar</h3>
                    <p className="text-sm text-muted-foreground">Prueba todo con tus primeros canales.</p>
                  </div>
                  <ul className="flex-1 space-y-3 text-sm">
                    {["Todos los canales", "API y webhooks completos", "Bandeja y plantillas", "Soporte por email"].map((x) => (
                      <li key={x} className="flex items-center gap-2.5">
                        <HugeiconsIcon icon={Tick02Icon} size={16} />
                        {x}
                      </li>
                    ))}
                  </ul>
                  <CtaLink href="/registro" variant="outline" className="self-start">
                    Crear cuenta
                  </CtaLink>
                </div>
                <div className="flex flex-col gap-8 rounded-3xl bg-foreground p-8 text-background">
                  <div className="space-y-2">
                    <h3 className="text-xl font-medium">Volumen</h3>
                    <p className="text-sm text-white/70">Para plataformas con muchos clientes conectados.</p>
                  </div>
                  <ul className="flex-1 space-y-3 text-sm">
                    {["Margen por mensaje a tu medida", "Facturación mensual", "Soporte prioritario", "Ayuda con la integración"].map((x) => (
                      <li key={x} className="flex items-center gap-2.5">
                        <HugeiconsIcon icon={Tick02Icon} size={16} />
                        {x}
                      </li>
                    ))}
                  </ul>
                  <CtaLink href="/registro" variant="light" className="self-start">
                    Hablemos
                  </CtaLink>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* FAQ */}
        <section id="faq" className="mx-auto w-full max-w-7xl scroll-mt-24 px-6">
          <SectionHead tag="FAQ" title="Preguntas frecuentes" />
          <div className="mt-12 space-y-3">
            {FAQ.map((f, i) => (
              <details key={f.q} open={i === 0} className="group rounded-3xl bg-muted/60 px-7 py-6 open:bg-muted">
                <summary className="flex cursor-pointer list-none items-center justify-between gap-6 text-base font-medium md:text-lg">
                  {f.q}
                  <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-background transition-transform group-open:rotate-45 group-open:bg-foreground group-open:text-background">
                    <HugeiconsIcon icon={PlusSignIcon} size={14} />
                  </span>
                </summary>
                <p className="mt-3 max-w-3xl text-sm leading-relaxed text-muted-foreground">{f.a}</p>
              </details>
            ))}
          </div>
        </section>

        {/* CTA final + footer */}
        <section className="px-3 md:px-5">
          <div className="relative overflow-hidden rounded-[40px] bg-foreground text-background">
            <DotPattern />
            <div className="relative mx-auto flex max-w-3xl flex-col items-center gap-6 px-6 py-24 text-center">
              <Tag dark>Empieza hoy</Tag>
              <h2 className="text-4xl font-medium tracking-tight md:text-6xl">
                Conecta tu primer canal esta tarde.
              </h2>
              <p className="text-base text-white/70 md:text-lg">
                Crea tu cuenta, genera tu clave y manda tu primer mensaje por WhatsApp.
              </p>
              <CtaLink href="/registro" variant="light">
                Crear cuenta
              </CtaLink>
            </div>

            <footer className="relative border-t border-white/10 px-6 py-12 md:px-14">
              <div className="mx-auto flex max-w-7xl flex-col gap-10 md:flex-row md:items-start md:justify-between">
                <div className="max-w-sm space-y-4">
                  <Logo tone="oscuro" />
                  <p className="text-sm text-white/60">
                    La pasarela omnicanal para conectar WhatsApp, Instagram y Messenger a tu producto.
                  </p>
                </div>
                <div className="grid grid-cols-2 gap-10 text-sm sm:grid-cols-3">
                  <div className="space-y-3">
                    <div className="font-medium">Producto</div>
                    {NAV.slice(0, 4).map((n) => (
                      <a key={n.href} href={n.href} className="block text-white/60 hover:text-white">
                        {n.label}
                      </a>
                    ))}
                  </div>
                  <div className="space-y-3">
                    <div className="font-medium">Legal</div>
                    <Link href="/privacy" className="block text-white/60 hover:text-white">
                      Privacidad
                    </Link>
                    <Link href="/terms" className="block text-white/60 hover:text-white">
                      Términos
                    </Link>
                    <Link href="/data-deletion" className="block text-white/60 hover:text-white">
                      Borrado de datos
                    </Link>
                  </div>
                  <div className="space-y-3">
                    <div className="font-medium">Cuenta</div>
                    <Link href="/login" className="block text-white/60 hover:text-white">
                      Entrar
                    </Link>
                    <Link href="/registro" className="block text-white/60 hover:text-white">
                      Crear cuenta
                    </Link>
                  </div>
                </div>
              </div>
              <div className="mx-auto mt-12 flex max-w-7xl items-center justify-between border-t border-white/10 pt-6 text-xs text-white/50">
                <span>© {new Date().getFullYear()} Sinapsa</span>
                <span>sinapsahq.com</span>
              </div>
            </footer>
          </div>
        </section>
      </main>
    </div>
  );
}
