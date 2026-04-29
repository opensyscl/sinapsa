import Link from "next/link";
import { HugeiconsIcon } from "@hugeicons/react";
import { Book01Icon, ArrowRight01Icon } from "@hugeicons/core-free-icons";
import { Card } from "@/components/ui/Card";

const DOCS = [
  {
    slug: "meta-setup",
    title: "Manual Meta para Sinapsa",
    description:
      "Pasos para registrar tu app Meta como Tech Provider y activar WhatsApp Cloud API, Instagram DM y Facebook Messenger.",
    minutes: 25,
  },
];

export default function DocsIndexPage() {
  return (
    <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-8 px-6 py-10">
      <div className="space-y-2">
        <div className="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">
          <HugeiconsIcon icon={Book01Icon} size={14} />
          Documentación
        </div>
        <h1 className="text-3xl font-semibold tracking-tight">Manuales y guías</h1>
        <p className="max-w-2xl text-sm text-muted-foreground">
          Documentación operativa para configurar Sinapsa, integraciones con Meta y
          flujos de Connect-as-a-Service.
        </p>
      </div>

      <div className="space-y-3">
        {DOCS.map((doc) => (
          <Link key={doc.slug} href={`/docs/${doc.slug}`}>
            <Card className="group flex items-start justify-between gap-4 p-6 transition-colors hover:bg-muted/40">
              <div className="space-y-1.5">
                <h2 className="text-lg font-semibold tracking-tight">{doc.title}</h2>
                <p className="text-sm text-muted-foreground">{doc.description}</p>
                <p className="text-xs text-muted-foreground">
                  ~{doc.minutes} min de lectura
                </p>
              </div>
              <HugeiconsIcon
                icon={ArrowRight01Icon}
                size={18}
                className="mt-1 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-1"
              />
            </Card>
          </Link>
        ))}
      </div>
    </main>
  );
}
