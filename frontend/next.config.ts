import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Build autocontenido para la imagen Docker de producción (ver Dockerfile)
  output: "standalone",
};

export default nextConfig;
