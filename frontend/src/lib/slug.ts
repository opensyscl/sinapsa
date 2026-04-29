/**
 * Convierte un nombre de workspace en un slug seguro:
 * - quita acentos / diacríticos
 * - pasa a minúsculas
 * - reemplaza cualquier no-alfanum por guion
 * - colapsa guiones repetidos y los recorta de los bordes
 */
export function slugify(input: string): string {
  return input
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 60);
}
