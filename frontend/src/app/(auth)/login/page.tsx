"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "sonner";
import { Button } from "@/components/ui/Button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/Card";
import { FieldError, Input, Label } from "@/components/ui/Input";
import { useLogin } from "@/lib/queries/auth";
import { useAuth } from "@/store/auth";

const schema = z.object({
  email: z.string().email("Email no válido"),
  password: z.string().min(1, "Contraseña requerida"),
});

type FormData = z.output<typeof schema>;

export default function LoginPage() {
  const router = useRouter();
  const token = useAuth((s) => s.token);
  const login = useLogin();

  useEffect(() => {
    if (token) router.replace("/dashboard");
  }, [token, router]);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormData>({ resolver: zodResolver(schema) });

  const onSubmit = (data: FormData) =>
    login.mutate(data, {
      onSuccess: () => {
        toast.success("Sesión iniciada");
        router.replace("/dashboard");
      },
      onError: (err) => {
        const e = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const msg =
          e.response?.data?.errors?.email?.[0] ??
          e.response?.data?.message ??
          "Error al iniciar sesión";
        toast.error(msg);
      },
    });

  return (
    <main className="flex flex-1 items-center justify-center px-6 py-12">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle>Entrar a Sinapsa</CardTitle>
          <CardDescription>Acceso operativo del workspace.</CardDescription>
        </CardHeader>
        <CardContent>
          <form className="space-y-4" onSubmit={handleSubmit(onSubmit)} noValidate>
            <div className="space-y-2">
              <Label htmlFor="email">Email</Label>
              <Input
                id="email"
                type="email"
                autoComplete="email"
                placeholder="hola@empresa.com"
                {...register("email")}
              />
              <FieldError message={errors.email?.message} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password">Contraseña</Label>
              <Input
                id="password"
                type="password"
                autoComplete="current-password"
                placeholder="••••••••"
                {...register("password")}
              />
              <FieldError message={errors.password?.message} />
            </div>
            <Button type="submit" className="w-full" disabled={login.isPending}>
              {login.isPending ? "Entrando…" : "Entrar"}
            </Button>
          </form>
          <p className="mt-6 text-center text-sm text-muted-foreground">
            ¿Sin cuenta?{" "}
            <Link href="/registro" className="font-medium underline-offset-2 hover:underline">
              Crear workspace
            </Link>
          </p>
        </CardContent>
      </Card>
    </main>
  );
}
