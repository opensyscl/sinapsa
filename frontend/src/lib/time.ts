import dayjs from "dayjs";

/** "hace 5 min", "hace 3 h", "ayer", "12/10" — recencia en palabras. */
export function ago(iso: string | null | undefined): string {
  if (!iso) return "—";
  const d = dayjs(iso);
  const min = dayjs().diff(d, "minute");
  if (min < 1) return "ahora";
  if (min < 60) return `hace ${min} min`;
  const h = Math.floor(min / 60);
  if (h < 24) return `hace ${h} h`;
  if (h < 48) return "ayer";
  return d.format("DD/MM");
}
