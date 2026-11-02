import Image from "next/image";
import { cn } from "@/lib/utils";

/**
 * Logo horizontal de Sinapsa (SVG del kit en /public/brand, sin modificar).
 * `claro` = tinta sobre fondo claro · `oscuro` = blanco sobre fondo oscuro.
 * Bajo 24 px de alto usar el símbolo pequeño, no este.
 */
export function Logo({
  tone = "claro",
  height = 28,
  className,
}: {
  tone?: "claro" | "oscuro";
  height?: number;
  className?: string;
}) {
  // viewBox del horizontal: 684 × 256
  const width = Math.round((height * 684) / 256);

  return (
    <Image
      src={`/brand/sinapsa-horizontal-${tone}.svg`}
      alt="Sinapsa"
      width={width}
      height={height}
      priority
      unoptimized
      className={cn("shrink-0", className)}
    />
  );
}
