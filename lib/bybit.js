const crypto = require("crypto");
const fs = require("fs");
const path = require("path");

function loadEnv() {
  const envPath = path.join(__dirname, "..", ".env");
  if (!fs.existsSync(envPath)) return;
  fs.readFileSync(envPath, "utf8")
    .split(/\r?\n/)
    .forEach((line) => {
      const trimmed = line.trim();
      if (!trimmed || trimmed.startsWith("#")) return;
      const eq = trimmed.indexOf("=");
      if (eq < 0) return;
      const key = trimmed.slice(0, eq).trim();
      const value = trimmed.slice(eq + 1).trim();
      if (!process.env[key]) process.env[key] = value;
    });
}

loadEnv();

const API_KEY = process.env.BYBIT_API_KEY || "";
const API_SECRET = process.env.BYBIT_API_SECRET || "";
const BASE = process.env.BYBIT_BASE || "https://api.bybit.com";

function sign(payload) {
  return crypto.createHmac("sha256", API_SECRET).update(payload).digest("hex");
}

function queryString(params) {
  return Object.keys(params)
    .filter((key) => params[key] !== undefined && params[key] !== "")
    .sort()
    .map((key) => key + "=" + params[key])
    .join("&");
}

async function bybitGet(pathname, params = {}) {
  const query = queryString(params);
  const timestamp = Date.now().toString();
  const recvWindow = "10000";
  const payload = timestamp + API_KEY + recvWindow + query;
  const url = BASE + pathname + (query ? "?" + query : "");
  const res = await fetch(url, {
    headers: {
      "X-BAPI-API-KEY": API_KEY,
      "X-BAPI-TIMESTAMP": timestamp,
      "X-BAPI-SIGN": sign(payload),
      "X-BAPI-RECV-WINDOW": recvWindow,
      Accept: "application/json",
    },
  });
  const json = await res.json();
  if (json.retCode !== 0) {
    throw new Error((json.retMsg || "Bybit error") + " (" + json.retCode + ")");
  }
  return json.result;
}

async function bybitWindowed(pathname, base, lookbackDays = 180, limit = 100) {
  const rows = [];
  let end = Math.floor(Date.now() / 1000);
  const oldest = end - lookbackDays * 86400;
  while (end > oldest) {
    const start = Math.max(oldest, end - 7 * 86400 + 1);
    let cursor = "";
    for (let i = 0; i < 8; i++) {
      const params = Object.assign({}, base, {
        startTime: String(start * 1000),
        endTime: String(end * 1000),
        limit: String(limit),
      });
      if (cursor) params.cursor = cursor;
      const result = await bybitGet(pathname, params);
      const list = result.list || [];
      rows.push.apply(rows, list);
      cursor = result.nextPageCursor || "";
      if (!cursor || list.length < limit) break;
    }
    end = start - 1;
  }
  return rows;
}

function uniqueRows(rows, keys) {
  const seen = new Set();
  const out = [];
  rows.forEach((row) => {
    const id = keys.map((key) => row[key] || "").join("|");
    if (!id || seen.has(id)) return;
    seen.add(id);
    out.push(row);
  });
  return out;
}

function num(value) {
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
}

function bybitToday() {
  return new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Shanghai" });
}

function dayKey(ms) {
  const ts = Number(ms);
  const date = new Date(ts > 0 ? ts : Date.now());
  return date.toLocaleDateString("en-CA", { timeZone: "Asia/Shanghai" });
}

function pickAccount(list) {
  const rows = list || [];
  return (
    rows.find((item) => num(item.totalEquity) > 0) ||
    rows.find((item) => num(item.totalWalletBalance) > 0) ||
    rows[0] ||
    null
  );
}

function usdtCoin(account) {
  return ((account && account.coin) || []).find((item) => item.coin === "USDT") || {};
}

async function fetchWallet() {
  const types = ["UNIFIED", "CONTRACT", "SPOT"];
  for (const accountType of types) {
    try {
      const result = await bybitGet("/v5/account/wallet-balance", { accountType });
      const account = pickAccount(result.list);
      if (account) {
        return { accountType, account };
      }
    } catch (error) {
      if (!String(error.message).includes("10001") && !String(error.message).includes("account")) {
        throw error;
      }
    }
  }
  return null;
}

function loadJson(name) {
  const file = path.join(__dirname, "..", "data", name);
  if (!fs.existsSync(file)) return [];
  try {
    const data = JSON.parse(fs.readFileSync(file, "utf8"));
    return Array.isArray(data) ? data : [];
  } catch (error) {
    return [];
  }
}

function saveJson(name, rows) {
  const file = path.join(__dirname, "..", "data", name);
  fs.writeFileSync(file, JSON.stringify(rows), "utf8");
}

async function fetchClosedPnl() {
  const stored = loadJson("bybit-closed.json");
  let lookback = 10;
  if (stored.length < 20) {
    lookback = 180;
  } else {
    const times = stored.map((row) => Number(row.updatedTime || 0));
    const spanDays = times.length ? (Math.max.apply(null, times) - Math.min.apply(null, times)) / 86400000 : 0;
    if (spanDays < 60) lookback = 180;
  }
  const fresh = await bybitWindowed(
    "/v5/position/closed-pnl",
    { category: "linear", symbol: "BTCUSDT" },
    lookback,
    100
  );
  const rows = uniqueRows(stored.concat(fresh), ["orderId", "updatedTime", "closedPnl"]);
  saveJson("bybit-closed.json", rows);
  return rows;
}

async function fetchPositions() {
  try {
    const result = await bybitGet("/v5/position/list", {
      category: "linear",
      symbol: "BTCUSDT",
    });
    return result.list || [];
  } catch (error) {
    return [];
  }
}

function aggregateDailyPnl(closed) {
  const days = {};
  closed.forEach((item) => {
    const day = dayKey(item.updatedTime || item.createdTime);
    days[day] = (days[day] || 0) + num(item.closedPnl);
  });
  return days;
}

function shiftDay(iso, delta) {
  const [y, m, d] = iso.split("-").map(Number);
  const date = new Date(Date.UTC(y, m - 1, d));
  date.setUTCDate(date.getUTCDate() + delta);
  return date.toISOString().slice(0, 10);
}

function buildEquity(wallet, closed) {
  const coin = usdtCoin(wallet);
  const equity = num((wallet && wallet.totalEquity) || coin.equity || (wallet && wallet.totalWalletBalance));
  const unrealized = num((wallet && wallet.totalPerpUPL) || coin.unrealisedPnl);
  let walletBal = num((wallet && wallet.totalWalletBalance) || coin.walletBalance);
  if (walletBal <= 0) walletBal = equity - unrealized;
  const daily = aggregateDailyPnl(closed);
  const today = bybitToday();
  const keys = Array.from(new Set(Object.keys(daily).concat([today]))).sort();
  const from = keys[0];
  const eod = {};
  let cursor = walletBal;
  for (let day = today; day >= from; day = shiftDay(day, -1)) {
    eod[day] = cursor;
    cursor -= daily[day] || 0;
  }
  const start = cursor;
  const base = Math.abs(start) > 1e-8 ? start : equity || 1;
  const series = Object.keys(eod)
    .sort()
    .map((day) => {
      const balance = day === today ? equity : eod[day];
      return {
        date: day,
        value: ((balance - base) / base) * 100,
        balance,
      };
    });
  return {
    equity,
    series,
    start: base,
    realized: Object.values(daily).reduce((sum, value) => sum + value, 0),
  };
}

function impliedStart(series) {
  for (const row of series) {
    const bal = num(row.balance);
    const val = num(row.value);
    const denom = 1 + val / 100;
    if (Math.abs(bal) > 1e-8 && Math.abs(denom) > 1e-8) return bal / denom;
  }
  const first = num(series[0] && series[0].balance);
  return Math.abs(first) > 1e-8 ? first : 1;
}

function rebaseSeries(series, start) {
  if (!series.length) return [];
  const rows = series.slice().sort((a, b) => String(a.date).localeCompare(String(b.date)));
  let base = start;
  if (base == null || Math.abs(base) < 1e-8) base = impliedStart(rows);
  if (Math.abs(base) < 1e-8) base = 1;
  return rows.map((row) => ({
    date: row.date,
    balance: num(row.balance),
    value: ((num(row.balance) - base) / base) * 100,
  }));
}

function periodReturn(series, days) {
  if (!series.length) return 0;
  const last = series[series.length - 1];
  const lastBal = num(last.balance);
  if (days === "all") return num(last.value);
  const target = shiftDay(last.date || bybitToday(), -Number(days));
  let then = null;
  series.forEach((row) => {
    if (row.date <= target) then = row;
  });
  if (!then) return num(last.value);
  const thenBal = num(then.balance);
  if (Math.abs(thenBal) < 1e-8) return 0;
  return ((lastBal - thenBal) / thenBal) * 100;
}

function snapshotDaily(file, equity) {
  const today = bybitToday();
  let rows = [];
  if (fs.existsSync(file)) {
    try {
      rows = JSON.parse(fs.readFileSync(file, "utf8"));
    } catch (error) {
      rows = [];
    }
  }
  if (!Array.isArray(rows)) rows = [];
  const existing = rows.find((row) => row.date === today);
  if (existing) existing.balance = equity;
  else rows.push({ date: today, balance: equity });
  rows.sort((a, b) => String(a.date).localeCompare(String(b.date)));
  fs.writeFileSync(file, JSON.stringify(rows, null, 2), "utf8");
  return rows;
}

function mergeSnapshots(series, snapshots) {
  const byDate = {};
  (series || []).forEach((row) => {
    if (row.date) byDate[row.date] = row;
  });
  (snapshots || []).forEach((row) => {
    if (!row.date) return;
    byDate[row.date] = {
      date: row.date,
      balance: num(row.balance),
      value: (byDate[row.date] && byDate[row.date].value) || 0,
    };
  });
  return rebaseSeries(Object.values(byDate), series && series.length ? impliedStart(series) : null);
}

function summarize(wallet, positions, series, realized) {
  const account = wallet && wallet.account;
  const coin = usdtCoin(account);
  const equity = num((account && account.totalEquity) || coin.equity || (account && account.totalWalletBalance));
  const pos = (positions || []).find((item) => num(item.size) > 0) || (positions && positions[0]) || {};
  let unrealized = num(pos.unrealisedPnl);
  if (Math.abs(unrealized) < 1e-12) {
    unrealized = num(coin.unrealisedPnl || (account && account.totalPerpUPL));
  }
  return {
    title: "BTC Trend · Bybit · тест",
    venue: "Bybit",
    instrument: "BTCUSDT Perp",
    accountType: wallet && wallet.accountType,
    createdAt: (series[0] && series[0].date) || bybitToday(),
    equity,
    currency: "USDT",
    profitLifetime: periodReturn(series, "all"),
    profit7Days: periodReturn(series, 7),
    profit30Days: periodReturn(series, 30),
    profit90Days: periodReturn(series, 90),
    unrealized,
    realized: num(realized),
    available: (() => {
      const fromAccount = num(account && (account.totalAvailableBalance || account.totalMarginBalance));
      return fromAccount > 0 ? fromAccount : num(coin.availableToWithdraw || coin.walletBalance);
    })(),
    position: {
      side: pos.side || "None",
      size: num(pos.size),
      avgPrice: num(pos.avgPrice),
      pnl: num(pos.unrealisedPnl || unrealized),
    },
  };
}

async function getBybitCase(cfg = {}) {
  if (!API_KEY || !API_SECRET) {
    throw new Error("Bybit keys are missing");
  }
  const since = cfg.since || "2026-08-31";
  const wallet = await fetchWallet();
  if (!wallet) throw new Error("Bybit wallet is empty");
  const [closedAll, positions] = await Promise.all([fetchClosedPnl(), fetchPositions()]);
  const closed = closedAll.filter((row) => dayKey(row.updatedTime || row.createdTime) >= since);
  const built = buildEquity(wallet.account, closed);
  const snapFile = path.join(__dirname, "..", "data", "bybit-daily.json");
  snapshotDaily(snapFile, built.equity);
  const series = rebaseSeries(built.series, built.start);
  const strategy = summarize(wallet, positions, series, built.realized);
  strategy.profitLifetime = periodReturn(series, "all");
  strategy.profit7Days = periodReturn(series, 7);
  strategy.profit30Days = periodReturn(series, 30);
  strategy.profit90Days = periodReturn(series, 90);
  return { strategy, series, source: "bybit-live" };
}

module.exports = { getBybitCase };
