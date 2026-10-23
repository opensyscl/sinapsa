// "Web X": un cliente SaaS de mentira que integra Sinapsa como lo haría uno real.
// - El backend (este archivo) guarda la sk_ y habla con la API de Sinapsa.
// - El frontend (index.html) solo recibe el session_token y usa el sdk.js de Sinapsa.
//
//   SINAPSA_API_KEY=sk_test_xxx node demo/saas-x/server.mjs   → http://localhost:4010
import { createServer } from "node:http";
import { readFileSync, existsSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));

// .env opcional junto al server (no se commitea)
const envFile = join(here, ".env");
if (existsSync(envFile)) {
  for (const line of readFileSync(envFile, "utf8").split("\n")) {
    const m = line.match(/^\s*([A-Z_]+)\s*=\s*(.*)\s*$/);
    if (m && !process.env[m[1]]) process.env[m[1]] = m[2];
  }
}

const PORT = Number(process.env.PORT ?? 4010);
const API = process.env.SINAPSA_API_URL ?? "http://localhost:48000";
const KEY = process.env.SINAPSA_API_KEY;
if (!KEY) {
  console.error("Falta SINAPSA_API_KEY (sk_test_... o sk_live_...)");
  process.exit(1);
}

async function sinapsa(method, path, body) {
  const res = await fetch(`${API}/api/v1${path}`, {
    method,
    headers: {
      Authorization: `Bearer ${KEY}`,
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  return { status: res.status, data: await res.json().catch(() => ({})) };
}

async function readJson(req) {
  let raw = "";
  for await (const chunk of req) raw += chunk;
  return raw ? JSON.parse(raw) : {};
}

function send(res, status, data) {
  res.writeHead(status, { "Content-Type": "application/json" });
  res.end(JSON.stringify(data));
}

createServer(async (req, res) => {
  const url = new URL(req.url, `http://localhost:${PORT}`);
  try {
    if (req.method === "GET" && url.pathname === "/") {
      res.writeHead(200, { "Content-Type": "text/html; charset=utf-8" });
      return res.end(readFileSync(join(here, "index.html")));
    }

    // 1) El usuario de Web X quiere conectar su calendario → pedimos una Connect Session
    if (req.method === "POST" && url.pathname === "/api/connect-session") {
      const { user_id } = await readJson(req);
      const r = await sinapsa("POST", "/connect-sessions", {
        allowed_channel_types: ["google_calendar"],
        display_label: "Conecta tu Google Calendar con Web X",
        end_user_id: user_id,
        client_metadata: { user_id },
      });
      return send(res, r.status, { session_token: r.data.session_token, error: r.data.error });
    }

    // 2) Proxy a la API de Sinapsa (la sk_ nunca llega al browser)
    if (req.method === "GET" && url.pathname === "/api/integrations") {
      const r = await sinapsa("GET", "/integrations?provider=google");
      return send(res, r.status, r.data);
    }

    const m = url.pathname.match(/^\/api\/integrations\/(\d+)\/(availability|events)$/);
    if (m) {
      const [, id, action] = m;
      if (action === "availability" && req.method === "GET") {
        const r = await sinapsa("GET", `/integrations/${id}/calendar/availability?${url.searchParams}`);
        return send(res, r.status, r.data);
      }
      if (action === "events" && req.method === "GET") {
        const r = await sinapsa("GET", `/integrations/${id}/calendar/events?${url.searchParams}`);
        return send(res, r.status, r.data);
      }
      if (action === "events" && req.method === "POST") {
        const r = await sinapsa("POST", `/integrations/${id}/calendar/events`, await readJson(req));
        return send(res, r.status, r.data);
      }
    }

    send(res, 404, { error: "not found" });
  } catch (e) {
    send(res, 500, { error: { message: String(e) } });
  }
}).listen(PORT, () => console.log(`Web X en http://localhost:${PORT} → Sinapsa ${API}`));
