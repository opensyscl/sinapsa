"use client";

import { useEffect, useRef } from "react";

/**
 * Ilustración del hero: un tetraedro (pirámide de base triangular) hecho de
 * caracteres que gira en 3D. Cada cara visible se sombrea según la luz y las
 * aristas van más brillantes.
 */
const RAMP = " .·:-=+*#%@";
const CELL_W = 6;
const CELL_H = 9;
const FPS = 24;
const LIGHT = norm([-0.45, 0.55, 0.7]);

type V3 = [number, number, number];

// Tetraedro regular centrado en el origen
const VERTS: V3[] = [
  [1, 1, 1],
  [1, -1, -1],
  [-1, 1, -1],
  [-1, -1, 1],
].map((v) => norm(v as V3));
const FACES: [number, number, number][] = [
  [0, 1, 2],
  [0, 3, 1],
  [0, 2, 3],
  [1, 3, 2],
];

function norm(v: V3): V3 {
  const l = Math.hypot(...v);
  return [v[0] / l, v[1] / l, v[2] / l];
}
const sub = (a: V3, b: V3): V3 => [a[0] - b[0], a[1] - b[1], a[2] - b[2]];
const cross = (a: V3, b: V3): V3 => [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]];
const dot = (a: V3, b: V3) => a[0] * b[0] + a[1] * b[1] + a[2] * b[2];

function rotate([x, y, z]: V3, ax: number, ay: number): V3 {
  // Y y después X
  const cy = Math.cos(ay), sy = Math.sin(ay);
  const x1 = x * cy + z * sy;
  const z1 = -x * sy + z * cy;
  const cx = Math.cos(ax), sx = Math.sin(ax);
  return [x1, y * cx - z1 * sx, y * sx + z1 * cx];
}

// Hash barato y determinista para el titileo de glifos
function hash(x: number, y: number, t: number) {
  const n = Math.sin(x * 127.1 + y * 311.7 + t * 74.7) * 43758.5453;
  return n - Math.floor(n);
}

type Face2D = {
  a: [number, number];
  b: [number, number];
  c: [number, number];
  area: number;
  light: number;
};

export function AsciiTriangle({ className }: { className?: string }) {
  const ref = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = ref.current;
    if (!canvas) return;
    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    let w = 0;
    let h = 0;
    let raf = 0;
    let last = 0;
    let visible = true;

    const resize = () => {
      const r = canvas.getBoundingClientRect();
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      w = r.width;
      h = r.height;
      canvas.width = Math.round(w * dpr);
      canvas.height = Math.round(h * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    };

    const render = (time: number) => {
      const t = time / 1000;
      ctx.clearRect(0, 0, w, h);
      ctx.font = `9px ui-monospace, SFMono-Regular, Menlo, monospace`;
      ctx.textBaseline = "top";
      ctx.fillStyle = "#ffffff";

      const size = Math.min(h * 0.5, w * 0.3);
      const cx = w / 2;
      const cy = h / 2;
      const ay = t * 0.5;
      const ax = 0.55 + Math.sin(t * 0.35) * 0.35;

      const pts = VERTS.map((v) => rotate(v, ax, ay));
      const faces: Face2D[] = [];
      for (const [i, j, k] of FACES) {
        let n = norm(cross(sub(pts[j], pts[i]), sub(pts[k], pts[i])));
        const centroid: V3 = [
          (pts[i][0] + pts[j][0] + pts[k][0]) / 3,
          (pts[i][1] + pts[j][1] + pts[k][1]) / 3,
          (pts[i][2] + pts[j][2] + pts[k][2]) / 3,
        ];
        if (dot(n, centroid) < 0) n = [-n[0], -n[1], -n[2]]; // normal hacia afuera
        if (n[2] <= 0) continue; // cara de espaldas a la cámara
        const p = (v: V3): [number, number] => [cx + v[0] * size, cy - v[1] * size];
        const a = p(pts[i]), b = p(pts[j]), c = p(pts[k]);
        const area = (b[0] - a[0]) * (c[1] - a[1]) - (c[0] - a[0]) * (b[1] - a[1]);
        if (Math.abs(area) < 1e-3) continue;
        faces.push({ a, b, c, area, light: Math.max(0, dot(n, LIGHT)) });
      }

      const frame = Math.floor(t * 8);
      const cols = Math.ceil(w / CELL_W);
      const rows = Math.ceil(h / CELL_H);
      const edge = 0.045;

      for (let row = 0; row < rows; row++) {
        const y = row * CELL_H + CELL_H / 2;
        for (let col = 0; col < cols; col++) {
          const x = col * CELL_W + CELL_W / 2;
          let v = 0;
          for (const f of faces) {
            // Coordenadas baricéntricas: dentro si las tres son >= 0
            const w0 = ((f.b[0] - x) * (f.c[1] - y) - (f.c[0] - x) * (f.b[1] - y)) / f.area;
            const w1 = ((f.c[0] - x) * (f.a[1] - y) - (f.a[0] - x) * (f.c[1] - y)) / f.area;
            const w2 = 1 - w0 - w1;
            if (w0 < 0 || w1 < 0 || w2 < 0) continue;
            v = Math.min(w0, w1, w2) < edge ? 1 : 0.18 + f.light * 0.62;
            break; // convexo + caras traseras descartadas: no hay solapes
          }
          if (v === 0) continue;

          // Ondas suaves que recorren las caras
          v *= 0.82 + 0.18 * Math.sin(col * 0.3 - t * 2 + Math.sin(row * 0.4 + t * 0.6));

          let i = Math.min(RAMP.length - 1, Math.max(1, Math.floor(v * RAMP.length)));
          const r = hash(col, row, frame);
          if (r > 0.9) i = Math.max(1, Math.min(RAMP.length - 1, i + (r > 0.95 ? 1 : -1)));

          ctx.globalAlpha = 0.2 + v * 0.7;
          ctx.fillText(RAMP[i], col * CELL_W, row * CELL_H);
        }
      }
      ctx.globalAlpha = 1;
    };

    const loop = (time: number) => {
      raf = requestAnimationFrame(loop);
      if (!visible || time - last < 1000 / FPS) return;
      last = time;
      render(time);
    };

    resize();
    const ro = new ResizeObserver(() => {
      resize();
      if (reduced) render(2000);
    });
    ro.observe(canvas);
    const io = new IntersectionObserver(([e]) => (visible = e.isIntersecting));
    io.observe(canvas);

    if (reduced) render(2000); // un frame fijo
    else raf = requestAnimationFrame(loop);

    return () => {
      cancelAnimationFrame(raf);
      ro.disconnect();
      io.disconnect();
    };
  }, []);

  return <canvas ref={ref} aria-hidden className={className} />;
}
