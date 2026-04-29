import { Card, CardDescription, CardHeader, CardTitle } from "@/components/ui/Card";

export default function DashboardPage() {
  return (
    <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-6 py-10">
      <div className="space-y-1">
        <h1 className="text-2xl font-semibold tracking-tight">Dashboard</h1>
        <p className="text-sm text-muted-foreground">
          Aún sin datos — el shell del workspace se construirá en Fase 1-4.
        </p>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Mensajes (24h)
            </CardTitle>
            <CardDescription className="text-3xl font-semibold text-foreground">
              0
            </CardDescription>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Conversaciones abiertas
            </CardTitle>
            <CardDescription className="text-3xl font-semibold text-foreground">
              0
            </CardDescription>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Canales conectados
            </CardTitle>
            <CardDescription className="text-3xl font-semibold text-foreground">
              0
            </CardDescription>
          </CardHeader>
        </Card>
      </div>
    </main>
  );
}
