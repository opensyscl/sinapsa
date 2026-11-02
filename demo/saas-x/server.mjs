// "Web X": un cliente SaaS de mentira que integra Sinapsa como lo haría uno real.
// Es lo que se publica en playground.sinapsahq.com.
// - El backend (este archivo) guarda la sk_ y habla con la API de Sinapsa.
// - El frontend (index.html) solo recibe el session_token y usa el sdk.js de Sinapsa.
// - Cada visitante es un "usuario de Web X" (id aleatorio en su navegador) = end_user_id en Sinapsa.
//
//   SINAPSA_API_KEY=sk_test_xxx node demo/saas-x/server.mjs   → http://localhost:4010
//   Contra producción: SINAPSA_API_URL=https://api.sinapsahq.com SINAPSA_APP_URL=https://app.sinapsahq.com
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
// De dónde se carga el sdk.js (el frontend de Sinapsa)
const APP = process.env.SINAPSA_APP_URL ?? "http://localhost:3002";
const KEY = process.env.SINAPSA_API_KEY;
if (!KEY) {
  console.error("Falta SINAPSA_API_KEY (sk_test_... o sk_live_...)");
  process.exit(1);
}

// Lo que Web X ofrece a sus usuarios. Solo Google Calendar está listo en Sinapsa.
const CONNECTABLE = ["google_calendar"];

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

// Cada respuesta lleva la llamada real a Sinapsa, para el panel "Qué pasó por debajo"
function sendCall(res, call, r) {
  send(res, r.status, { ...r.data, _sinapsa: { ...call, status: r.status } });
}

// Usuario de Web X: viene en la cabecera, generado en el navegador
const USER_RE = /^webx-[a-z0-9]{8,32}$/;
function userOf(req) {
  const id = req.headers["x-webx-user"];
  return typeof id === "string" && USER_RE.test(id) ? id : null;
}

// Solo se puede tocar una integración del propio usuario
async function ownIntegration(id, user) {
  const r = await sinapsa("GET", `/integrations/${id}`);
  return r.status === 200 && r.data.end_user_id === user;
}

createServer(async (req, res) => {
  const url = new URL(req.url, `http://localhost:${PORT}`);
  try {
    if (req.method === "GET" && url.pathname === "/") {
      res.writeHead(200, { "Content-Type": "text/html; charset=utf-8" });
      return res.end(readFileSync(join(here, "index.html"), "utf8").replaceAll("http://localhost:3002", APP));
    }

    if (!url.pathname.startsWith("/api/")) return send(res, 404, { error: "not found" });

    const user = userOf(req);
    if (!user) return send(res, 401, { error: { message: "Falta el usuario de Web X" } });

    // 1) El usuario quiere conectar algo → pedimos una Connect Session con la sk_
    if (req.method === "POST" && url.pathname === "/api/connect-session") {
      const { types = [], name } = await readJson(req);
      const allowed = types.filter((t) => CONNECTABLE.includes(t));
      if (!allowed.length) return send(res, 422, { error: { message: "Nada que conectar" } });
      const body = {
        allowed_channel_types: allowed,
        display_label: "Web X",
        end_user_id: user,
        client_metadata: { name: String(name ?? "").slice(0, 60) },
      };
      const r = await sinapsa("POST", "/connect-sessions", body);
      return sendCall(res, { method: "POST", path: "/api/v1/connect-sessions", body }, r);
    }

    // 2) Integraciones de ESTE usuario
    if (req.method === "GET" && url.pathname === "/api/integrations") {
      const path = `/integrations?end_user_id=${encodeURIComponent(user)}`;
      const r = await sinapsa("GET", path);
      return sendCall(res, { method: "GET", path: `/api/v1${path}` }, r);
    }

    const m = url.pathname.match(/^\/api\/integrations\/(\d+)(?:\/(availability|events)(?:\/([\w-]+))?)?$/);
    if (m) {
      const [, id, action, eventId] = m;
      if (!(await ownIntegration(id, user))) return send(res, 404, { error: { message: "No es tuya" } });

      let call;
      if (!action && req.method === "DELETE") call = { method: "DELETE", path: `/integrations/${id}` };
      else if (action === "availability" && req.method === "GET")
        call = { method: "GET", path: `/integrations/${id}/calendar/availability?${url.searchParams}` };
      else if (action === "events" && !eventId && req.method === "GET")
        call = { method: "GET", path: `/integrations/${id}/calendar/events?${url.searchParams}` };
      else if (action === "events" && !eventId && req.method === "POST")
        call = { method: "POST", path: `/integrations/${id}/calendar/events`, body: await readJson(req) };
      else if (action === "events" && eventId && req.method === "DELETE")
        call = { method: "DELETE", path: `/integrations/${id}/calendar/events/${eventId}` };

      if (call) {
        const r = await sinapsa(call.method, call.path, call.body);
        return sendCall(res, { ...call, path: `/api/v1${call.path}` }, r);
      }
    }

    send(res, 404, { error: "not found" });
  } catch (e) {
    send(res, 500, { error: { message: String(e) } });
  }
}).listen(PORT, () => console.log(`Web X en http://localhost:${PORT} → Sinapsa ${API}`));
