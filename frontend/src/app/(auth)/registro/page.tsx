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
import { useRegister } from "@/lib/queries/auth";
import { useAuth } from "@/store/auth";
import { slugify } from "@/lib/slug";

const schema = z
  .object({
    workspace_name: z.string().min(2, "Nombre del workspace muy corto").max(120),
    workspace_slug: z
      .string()
      .min(3, "Mínimo 3 caracteres")
      .max(60)
      .regex(/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/, "Solo minúsculas, números y guiones"),
    name: z.string().min(2, "Tu nombre"),
    email: z.string().email("Email no válido"),
    password: z.string().min(8, "Mínimo 8 caracteres"),
    password_confirmation: z.string().min(1, "Confirma la contraseña"),
  })
  .refine((d) => d.password === d.password_confirmation, {
    message: "Las contraseñas no coinciden",
    path: ["password_confirmation"],
  });

type FormData = z.output<typeof schema>;

export default function RegisterPage() {
  const router = useRouter();
  const token = useAuth((s) => s.token);
  const register = useRegister();

  useEffect(() => {
    if (token) router.replace("/dashboard");
  }, [token, router]);

  const form = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      workspace_name: "",
      workspace_slug: "",
      name: "",
      email: "",
      password: "",
      password_confirmation: "",
    },
  });

  const {
    register: r,
    handleSubmit,
    formState: { errors },
    watch,
    setValue,
    getFieldState,
  } = form;

  // Auto-slug del nombre del workspace SI el usuario no ha tocado el slug a mano.
  const wsName = watch("workspace_name");
  useEffect(() => {
    const slugDirty = getFieldState("workspace_slug").isTouched;
    if (!slugDirty) {
      setValue("workspace_slug", slugify(wsName ?? ""), { shouldValidate: true });
    }
  }, [wsName, setValue, getFieldState]);

  const onSubmit = (data: FormData) =>
    register.mutate(data, {
      onSuccess: () => {
        toast.success("Workspace creado · 14 días de prueba");
        router.replace("/dashboard");
      },
      onError: (err) => {
        const e = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const fieldErrors = e.response?.data?.errors;
        if (fieldErrors) {
          for (const [field, msgs] of Object.entries(fieldErrors)) {
            toast.error(`${field}: ${msgs[0]}`);
          }
        } else {
          toast.error(e.response?.data?.message ?? "Error al crear workspace");
        }
      },
    });

  return (
    <main className="flex flex-1 items-center justify-center px-6 py-12">
      <Card className="w-full max-w-lg">
        <CardHeader>
          <CardTitle>Crea tu workspace</CardTitle>
          <CardDescription>
            14 días de prueba gratis. Sin tarjeta. Cancelas cuando quieras.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form className="space-y-4" onSubmit={handleSubmit(onSubmit)} noValidate>
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="workspace_name">Nombre del workspace</Label>
                <Input id="workspace_name" placeholder="Acme S.L." {...r("workspace_name")} />
                <FieldError message={errors.workspace_name?.message} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="workspace_slug">Slug</Label>
                <Input id="workspace_slug" placeholder="acme" {...r("workspace_slug")} />
                <p className="text-xs text-muted-foreground">
                  {watch("workspace_slug") || "tu-empresa"}.sinapsa.app
                </p>
                <FieldError message={errors.workspace_slug?.message} />
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="name">Tu nombre</Label>
              <Input id="name" placeholder="Marta García" {...r("name")} />
              <FieldError message={errors.name?.message} />
            </div>

            <div className="space-y-2">
              <Label htmlFor="email">Email</Label>
              <Input id="email" type="email" autoComplete="email" placeholder="hola@empresa.com" {...r("email")} />
              <FieldError message={errors.email?.message} />
            </div>

            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="password">Contraseña</Label>
                <Input
                  id="password"
                  type="password"
                  autoComplete="new-password"
                  placeholder="al menos 8 caracteres"
                  {...r("password")}
                />
                <FieldError message={errors.password?.message} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="password_confirmation">Repite contraseña</Label>
                <Input
                  id="password_confirmation"
                  type="password"
                  autoComplete="new-password"
                  {...r("password_confirmation")}
                />
                <FieldError message={errors.password_confirmation?.message} />
              </div>
            </div>

            <Button type="submit" className="w-full" disabled={register.isPending}>
              {register.isPending ? "Creando workspace…" : "Crear workspace"}
            </Button>
          </form>

          <p className="mt-6 text-center text-sm text-muted-foreground">
            ¿Ya tienes cuenta?{" "}
            <Link href="/login" className="font-medium underline-offset-2 hover:underline">
              Entrar
            </Link>
          </p>
        </CardContent>
      </Card>
    </main>
  );
}
