"use client";

import dayjs from "dayjs";

type Bucket = { hour: string; total: number; errors: number };

/** Escala redonda de 4 pasos con el tope por encima del máximo: 0/1/2/3/4, 0/5/10/15/20… */
function niceScale(max: number): number[] {
  const steps = [1, 2, 5, 10, 25, 50, 100, 250, 500, 1000, 2500, 5000, 10000, 25000];
  const step = steps.find((s) => s * 4 > max) ?? Math.ceil((max + 1) / 4);
  return [0, 1, 2, 3, 4].map((k) => k * step);
}

/**
 * Columnas por hora de las últimas 24 h: las correctas en el color de datos,
 * las fallidas encima en rojo con una costura de 1px. Esquinas rectas, sin leyenda
 * (el título dice qué es), ticks redondos, una etiqueta cada 6 horas.
 */
export function HourlyBars({ data, height = 176 }: { data: Bucket[]; height?: number }) {
  const ticks = niceScale(Math.max(0, ...data.map((d) => d.total)));
  const top = ticks[ticks.length - 1];
  const W = 640;
  const left = 32;
  const bottom = 22;
  const plotH = height - bottom - 6;
  const slot = (W - left) / data.length;
  const bar = Math.max(2, slot - 4);
  const y = (v: number) => 6 + plotH - (v / top) * plotH;

  return (
    <svg viewBox={`0 0 ${W} ${height}`} className="block h-auto w-full" role="img" aria-label="Llamadas por hora en las últimas 24 horas">
      {ticks.map((t) => (
        <g key={t}>
          <line x1={left} x2={W} y1={y(t)} y2={y(t)} stroke={t === 0 ? "var(--muted-foreground)" : "var(--rule)"} strokeWidth={1} />
          <text x={left - 8} y={y(t)} dy="0.32em" textAnchor="end" fontSize={11} fill="var(--muted-foreground)" className="tabular-nums">
            {t}
          </text>
        </g>
      ))}
      {data.map((d, i) => {
        const x = left + i * slot + (slot - bar) / 2;
        const ok = d.total - d.errors;
        const label = `${dayjs(d.hour).format("HH:mm")} · ${d.total} llamadas${d.errors ? `, ${d.errors} con error` : ""}`;
        return (
          <g key={d.hour}>
            <title>{label}</title>
            {ok > 0 && <rect x={x} y={y(ok)} width={bar} height={y(0) - y(ok)} fill="var(--data)" />}
            {d.errors > 0 && (
              <rect x={x} y={y(d.total)} width={bar} height={Math.max(1, y(ok) - y(d.total) - (ok > 0 ? 1 : 0))} fill="var(--destructive)" />
            )}
            {i % 6 === 0 && (
              <text x={x + bar / 2} y={height - 6} textAnchor="middle" fontSize={11} fill="var(--muted-foreground)" className="tabular-nums">
                {dayjs(d.hour).format("HH:mm")}
              </text>
            )}
          </g>
        );
      })}
    </svg>
  );
}
