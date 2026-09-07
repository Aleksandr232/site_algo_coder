const http = require("http");
const fs = require("fs");
const path = require("path");
const { getBybitCase } = require("./lib/bybit");

const PORT = Number(process.env.PORT || 8080);
const ROOT = __dirname;
const DATA_DIR = path.join(ROOT, "data");
const COMON_ORIGIN = "https://www.comon.ru";
const DEFAULT_ID = "131208";

const MIME = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".svg": "image/svg+xml",
  ".ico": "image/x-icon",
  ".png": "image/png",
  ".woff2": "font/woff2",
};

function send(res, status, body, headers = {}) {
  const payload = Buffer.isBuffer(body) ? body : Buffer.from(body);
  res.writeHead(status, {
    "Content-Length": payload.length,
    "Cache-Control": "no-store",
    ...headers,
  });
  res.end(payload);
}

function sendJson(res, status, obj) {
  send(res, status, JSON.stringify(obj), {
    "Content-Type": "application/json; charset=utf-8",
  });
}

function safeJoin(root, urlPath) {
  const decoded = decodeURIComponent(urlPath.split("?")[0]);
  const clean = decoded.replace(/^\/+/, "") || "index.html";
  const full = path.normalize(path.join(root, clean));
  if (!full.startsWith(root)) return null;
  return full;
}

function readCache(file) {
  const full = path.join(DATA_DIR, file);
  if (!fs.existsSync(full)) return null;
  return fs.readFileSync(full, "utf8");
}

function writeCache(file, text) {
  fs.mkdirSync(DATA_DIR, { recursive: true });
  fs.writeFileSync(path.join(DATA_DIR, file), text, "utf8");
}

function requestHeaders(cookie) {
  const headers = {
    "User-Agent":
      "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36",
    Accept: "application/json, text/plain, */*",
    Referer: COMON_ORIGIN + "/strategies/" + DEFAULT_ID + "/",
  };
  if (cookie) headers.Cookie = cookie;
  return headers;
}

function cookieFrom(res) {
  const parts = typeof res.headers.getSetCookie === "function" ? res.headers.getSetCookie() : [];
  return parts
    .map((item) => String(item).split(";")[0])
    .filter(Boolean)
    .join("; ");
}

async function pullComon(apiPath) {
  const url = COMON_ORIGIN + apiPath;
  let res = await fetch(url, { headers: requestHeaders(), redirect: "manual" });

  if (res.status >= 300 && res.status < 400) {
    const cookie = cookieFrom(res);
    if (!cookie) throw new Error("Comon redirect without session");
    res = await fetch(url, { headers: requestHeaders(cookie), redirect: "manual" });
  }

  const text = await res.text();
  if (!res.ok) {
    throw new Error("Comon " + res.status);
  }
  JSON.parse(text);
  return text;
}

async function handleComon(res, id, kind) {
  if (!/^\d+$/.test(id)) {
    sendJson(res, 400, { error: "bad strategy id" });
    return;
  }

  const apiPath =
    kind === "profit"
      ? "/api/v1/strategies/" + id + "/profit"
      : "/api/v1/strategies/" + id;
  const cacheFile =
    kind === "profit" ? "strategy-" + id + "-profit.json" : "strategy-" + id + ".json";

  try {
    const text = await pullComon(apiPath);
    writeCache(cacheFile, text);
    send(res, 200, text, {
      "Content-Type": "application/json; charset=utf-8",
      "X-Data-Source": "comon-live",
    });
  } catch (error) {
    const cached = readCache(cacheFile);
    if (cached) {
      send(res, 200, cached, {
        "Content-Type": "application/json; charset=utf-8",
        "X-Data-Source": "cache",
        "X-Data-Error": String(error.message || error),
      });
      return;
    }
    sendJson(res, 502, { error: "comon unavailable", detail: String(error.message || error) });
  }
}

const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, "http://127.0.0.1");
  const route = url.pathname.replace(/\/+$/, "") || "/";

  const live = route.match(/^\/api\/comon\/(\d+)(?:\/(profit))?$/);
  if (live) {
    await handleComon(res, live[1], live[2] || "strategy");
    return;
  }

  if (route === "/api/bybit/case") {
    try {
      const payload = await getBybitCase();
      const text = JSON.stringify(payload);
      writeCache("bybit-case.json", text);
      send(res, 200, text, {
        "Content-Type": "application/json; charset=utf-8",
        "X-Data-Source": "bybit-live",
      });
    } catch (error) {
      const cached = readCache("bybit-case.json");
      if (cached) {
        send(res, 200, cached, {
          "Content-Type": "application/json; charset=utf-8",
          "X-Data-Source": "cache",
          "X-Data-Error": String(error.message || error),
        });
        return;
      }
      sendJson(res, 502, { error: "bybit unavailable", detail: String(error.message || error) });
    }
    return;
  }

  if (route === "/api/health") {
    sendJson(res, 200, { ok: true, strategyId: DEFAULT_ID, bybit: true });
    return;
  }

  let filePath = safeJoin(ROOT, route === "/" ? "/index.html" : route);
  if (!filePath) {
    send(res, 403, "Forbidden", { "Content-Type": "text/plain; charset=utf-8" });
    return;
  }

  if (fs.existsSync(filePath) && fs.statSync(filePath).isDirectory()) {
    filePath = path.join(filePath, "index.html");
  }

  if (!fs.existsSync(filePath) || !fs.statSync(filePath).isFile()) {
    send(res, 404, "Not found", { "Content-Type": "text/plain; charset=utf-8" });
    return;
  }

  const ext = path.extname(filePath).toLowerCase();
  send(res, 200, fs.readFileSync(filePath), {
    "Content-Type": MIME[ext] || "application/octet-stream",
    "Cache-Control": ext === ".html" || ext === ".js" || ext === ".json" ? "no-store" : "public, max-age=3600",
  });
});

server.listen(PORT, "127.0.0.1", () => {
  console.log("AM QuantLab: http://127.0.0.1:" + PORT + "/");
  console.log("Live Comon proxy: /api/comon/" + DEFAULT_ID);
  console.log("Live Bybit case: /api/bybit/case");
});
