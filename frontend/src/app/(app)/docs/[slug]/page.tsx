import fs from "node:fs/promises";
import path from "node:path";
import Link from "next/link";
import { notFound } from "next/navigation";
import { HugeiconsIcon } from "@hugeicons/react";
import { ArrowLeft01Icon, Book01Icon } from "@hugeicons/core-free-icons";
import { MarkdownView } from "@/components/docs/MarkdownView";

const DOC_INDEX: Record<string, { file: string; title: string }> = {
  "meta-setup": {
    file: "META_SETUP.md",
    title: "Manual Meta para Sinapsa",
  },
};

/**
 * Renderiza un manual MD desde el directorio `docs/` del repo.
 * Server Component — lee el archivo en build/request time.
 */
export default async function DocPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const meta = DOC_INDEX[slug];
  if (!meta) {
    notFound();
  }

  // process.cwd() en next dev/build = directorio frontend.
  // El docs/ vive un nivel arriba, en la raíz del repo.
  const filePath = path.resolve(process.cwd(), "..", "docs", meta.file);
  let source = "";
  try {
    source = await fs.readFile(filePath, "utf-8");
  } catch {
    notFound();
  }

  return (
    <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 px-6 py-10">
      <div className="space-y-1">
        <Link
          href="/docs"
          className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
        >
          <HugeiconsIcon icon={ArrowLeft01Icon} size={12} />
          Volver a manuales
        </Link>
        <div className="flex items-center gap-2 pt-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">
          <HugeiconsIcon icon={Book01Icon} size={14} />
          Documentación
        </div>
      </div>

      <article>
        <MarkdownView source={source} />
      </article>
    </main>
  );
}
