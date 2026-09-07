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

async function bybitGet(pathname, params = {}) {
  const query = Object.keys(params)
    .filter((key) => params[key] !== undefined && params[key] !== "")
    .sort()
    .map((key) => key + "=" + encodeURIComponent(params[key]))
    .join("&");
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

function num(value) {
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
}

function dayKey(ms) {
  return new Date(Number(ms)).toISOString().slice(0, 10);
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

async function fetchClosedPnl() {
  const rows = [];
  let cursor = "";
  for (let i = 0; i < 6; i++) {
    const result = await bybitGet("/v5/position/closed-pnl", {
      category: "linear",
      symbol: "BTCUSDT",
      limit: 100,
      cursor,
    });
    const list = result.list || [];
    rows.push.apply(rows, list);
    cursor = result.nextPageCursor || "";
    if (!cursor || list.length < 100) break;
  }
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
    if (!days[day]) days[day] = 0;
    days[day] += num(item.closedPnl);
  });
  return days;
}

function buildEquity(wallet, closed) {
  const equity = num(wallet && (wallet.totalEquity || wallet.totalWalletBalance));
  const unrealized = num(wallet && wallet.totalPerpUPL);
  const daily = aggregateDailyPnl(closed);
  const days = Object.keys(daily).sort();
  if (!days.length) {
    const today = new Date().toISOString().slice(0, 10);
    return {
      equity,
      series: [{ date: today, value: 0, balance: equity }],
      daily,
    };
  }

  let cursor = equity - unrealized;
  const backwards = [];
  for (let i = days.length - 1; i >= 0; i--) {
    const day = days[i];
    backwards.push({ date: day, balance: cursor, pnl: daily[day] });
    cursor -= daily[day];
  }
  backwards.reverse();
  const start = backwards[0].balance - (daily[backwards[0].date] || 0);
  const base = Math.abs(start) > 1e-8 ? start : equity || 1;
  const series = backwards.map((row) => ({
    date: row.date,
    value: ((row.balance - base) / base) * 100,
    balance: row.balance,
  }));
  const today = new Date().toISOString().slice(0, 10);
  const last = series[series.length - 1];
  if (!last || last.date !== today) {
    series.push({
      date: today,
      value: ((equity - base) / base) * 100,
      balance: equity,
    });
  } else {
    last.balance = equity;
    last.value = ((equity - base) / base) * 100;
  }
  return { equity, series, daily, startBalance: base };
}

function periodReturn(series, days) {
  if (!series.length) return 0;
  const last = series[series.length - 1].value;
  if (days === "all") return last;
  const from = series[Math.max(0, series.length - days)].value;
  return ((100 + last) / (100 + from) - 1) * 100;
}

function snapshotDaily(file, equity) {
  const today = new Date().toISOString().slice(0, 10);
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
  rows.sort((a, b) => a.date.localeCompare(b.date));
  fs.writeFileSync(file, JSON.stringify(rows, null, 2), "utf8");
  return rows;
}

function mergeSnapshots(series, snapshots) {
  if (!snapshots || snapshots.length < 2) return series;
  const start = snapshots[0].balance || 1;
  const fromSnap = snapshots.map((row) => ({
    date: row.date,
    value: ((row.balance - start) / start) * 100,
    balance: row.balance,
  }));
  const byDate = {};
  series.forEach((row) => {
    byDate[row.date] = row;
  });
  fromSnap.forEach((row) => {
    byDate[row.date] = row;
  });
  return Object.values(byDate).sort((a, b) => a.date.localeCompare(b.date));
}

function summarize(wallet, positions, series) {
  const account = wallet && wallet.account;
  const equity = num(account && (account.totalEquity || account.totalWalletBalance));
  const coin = ((account && account.coin) || []).find((item) => item.coin === "USDT" || item.coin === "BTC") || {};
  const pos = (positions || []).find((item) => num(item.size) > 0) || positions[0] || {};
  const last = series[series.length - 1] || { value: 0 };
  return {
    title: "BTC Trend · Bybit · тест",
    venue: "Bybit",
    instrument: "BTCUSDT Perp",
    accountType: wallet && wallet.accountType,
    createdAt: (series[0] && series[0].date) || new Date().toISOString().slice(0, 10),
    equity,
    currency: coin.coin || "USDT",
    profitLifetime: last.value,
    profit7Days: periodReturn(series, 7),
    profit30Days: periodReturn(series, 30),
    profit90Days: periodReturn(series, 90),
    unrealized: num(account && account.totalPerpUPL),
    realized: num(coin.cumRealisedPnl || account.totalClosedPnl),
    available: num(account && (account.totalAvailableBalance || account.totalMarginBalance)),
    position: {
      side: pos.side || "None",
      size: num(pos.size),
      avgPrice: num(pos.avgPrice),
      pnl: num(pos.unrealisedPnl),
    },
  };
}

async function getBybitCase() {
  if (!API_KEY || !API_SECRET) {
    throw new Error("Bybit keys are missing");
  }
  const wallet = await fetchWallet();
  if (!wallet) throw new Error("Bybit wallet is empty");
  const [closed, positions] = await Promise.all([fetchClosedPnl(), fetchPositions()]);
  const built = buildEquity(wallet.account, closed);
  const snapFile = path.join(__dirname, "..", "data", "bybit-daily.json");
  const snapshots = snapshotDaily(snapFile, built.equity);
  const series = mergeSnapshots(built.series, snapshots);
  const strategy = summarize(wallet, positions, series);
  strategy.profitLifetime = periodReturn(series, "all");
  strategy.profit7Days = periodReturn(series, 7);
  strategy.profit30Days = periodReturn(series, 30);
  strategy.profit90Days = periodReturn(series, 90);
  return { strategy, series, source: "bybit-live" };
}

module.exports = { getBybitCase };
