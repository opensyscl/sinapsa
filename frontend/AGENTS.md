<!-- BEGIN:nextjs-agent-rules -->
# This is NOT the Next.js you know

This version (16.x) has breaking changes — APIs, conventions, and file structure may differ from your training data. When in doubt, read the relevant guide in `node_modules/next/dist/docs/` before writing code. Heed deprecation notices.
<!-- END:nextjs-agent-rules -->

# Sinapsa frontend — convenciones

- **Tipografía:** Euclid Circular B servida desde `/public/fonts/`. Nada de Geist, Inter, Arial. Cargada vía `@font-face` en [globals.css](src/app/globals.css).
- **Iconos:** SOLO `@hugeicons/react` + `@hugeicons/core-free-icons`. Nunca Lucide, Heroicons o emojis.
- **Panel:** estilo "framed sheet" (skill `design-taste-frontend`, ver `.taste-skill/direction.json`): menú lateral sobre el marco `bg-frame`, contenido en una hoja blanca `rounded-2xl`. Cards `rounded-lg border border-border` sin sombra (helper en [components/ui/Card.tsx](src/components/ui/Card.tsx)). Ítem activo en gris neutro (`bg-active`), nunca negro ni de color. `--data` (teal) solo para la serie principal de un gráfico. Títulos 22px, cifras en `tabular-nums`, sin iconos en cuadros tintados.
- **Landing y páginas públicas:** cards `rounded-3xl` como antes.
- **Botones:** primarios negros (`bg-primary text-primary-foreground`), nada de gradientes ni look "AI button".
- **Tema:** sobrio, blanco, acentos negros. Verde solo positivo, rojo solo negativo. Variables CSS en [globals.css](src/app/globals.css).
- **Estado:** [TanStack Query](src/components/Providers.tsx) para server state, [Zustand persist](src/store/auth.ts) para auth. Nada de Redux ni Context para datos del servidor.
- **API client:** [lib/api.ts](src/lib/api.ts) (axios + bearer token desde Zustand persist). Nunca llamar a `fetch` raw para endpoints autenticados.
- **Formularios:** RHF + Zod. Patrón con `z.input` / `z.output` + `useForm<FormInput, unknown, FormData>` (cuando se usen `z.coerce`).
- **Rutas:** segmentos `(auth)` para login/registro y `(app)` para dashboard autenticado. `(public)/p/[slug]` para escaparates futuros.
- **API base URL:** `process.env.NEXT_PUBLIC_API_URL` (en Docker `http://localhost:48000`).

## Colores y tokens disponibles

`bg-background`, `bg-foreground`, `bg-muted`, `bg-primary`, `bg-secondary`, `bg-accent`, `bg-positive`, `bg-warning`, `bg-destructive` y sus `text-*-foreground` correspondientes. Borde por defecto: `border-border`.
