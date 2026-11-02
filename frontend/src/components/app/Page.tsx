import { cn } from "@/lib/utils";

/** Contenido de una pantalla del panel dentro de la hoja blanca. */
export function Page({ className, children }: { className?: string; children: React.ReactNode }) {
  return <main className={cn("flex w-full flex-1 flex-col gap-10 px-5 py-6 md:px-8", className)}>{children}</main>;
}

/** Fila de título: un sustantivo a 22px, una línea con el dato que importa y, a la derecha, solo acciones reales. */
export function PageHeader({
  title,
  subtitle,
  actions,
}: {
  title: string;
  subtitle?: React.ReactNode;
  actions?: React.ReactNode;
}) {
  return (
    <div className="flex flex-wrap items-start justify-between gap-4">
      <div className="min-w-0 space-y-1">
        <h1 className="text-[22px] font-semibold leading-tight tracking-tight">{title}</h1>
        {subtitle && <p className="text-sm text-muted-foreground">{subtitle}</p>}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </div>
  );
}

/** Bloque con título de 16px a la izquierda y una acción discreta a la derecha. */
export function Section({
  title,
  meta,
  action,
  className,
  children,
}: {
  title: string;
  meta?: React.ReactNode;
  action?: React.ReactNode;
  className?: string;
  children: React.ReactNode;
}) {
  return (
    <section className={cn("min-w-0 space-y-3", className)}>
      <div className="flex items-baseline justify-between gap-3">
        <h2 className="text-base font-medium">
          {title}
          {meta && <span className="ml-2 text-sm font-normal text-muted-foreground">{meta}</span>}
        </h2>
        {action}
      </div>
      {children}
    </section>
  );
}
