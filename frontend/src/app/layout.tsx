import type { Metadata, Viewport } from "next";
import "./globals.css";
import { Providers } from "@/components/Providers";

export const metadata: Metadata = {
  title: "Sinapsa — pasarela omnicanal Meta",
  description:
    "Conecta WhatsApp, Instagram y Messenger en una sola API. Bandeja unificada, webhooks normalizados, multi-empresa.",
  // TODO: descomentar cuando haya dominio (las URLs de OG deben ser absolutas)
  // metadataBase: new URL("https://sinapsa.app"),
  // openGraph: {
  //   title: "Sinapsa — pasarela omnicanal Meta",
  //   description:
  //     "Conecta WhatsApp, Instagram y Messenger en una sola API. Bandeja unificada, webhooks normalizados, multi-empresa.",
  //   siteName: "Sinapsa",
  //   images: [{ url: "/og-image.png", width: 1200, height: 630, alt: "Sinapsa" }],
  // },
  // twitter: {
  //   card: "summary_large_image",
  //   images: ["/og-image.png"],
  // },
};

export const viewport: Viewport = {
  themeColor: "#0B0B0C",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="es" className="h-full antialiased">
      <body className="min-h-full flex flex-col bg-background text-foreground">
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
