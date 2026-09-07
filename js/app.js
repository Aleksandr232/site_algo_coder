(function () {
  const STRATEGY_ID = (window.COMON_SOURCE && window.COMON_SOURCE.id) || 131208;
  const REFRESH_MS = 20 * 1000;
  let loadingLive = false;
  let loadingBybit = false;

  const state = {
    strategy: window.COMON_STRATEGY,
    equity: window.COMON_EQUITY || [],
    source: "fallback",
    fetchedAt: null,
  };

  const bybit = {
    strategy: null,
    equity: [],
    source: "fallback",
    fetchedAt: null,
  };

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

  const fmtPct = (value, digits = 1) => {
    const sign = value > 0 ? "+" : "";
    return sign + Number(value).toFixed(digits) + "%";
  };

  const fmtDate = (iso) => {
    if (!iso) return "—";
    const day = String(iso).slice(0, 10);
    const [y, m, d] = day.split("-");
    return d + "." + m + "." + y;
  };

  const fmtTime = (date) =>
    date.toLocaleTimeString("ru-RU", { hour: "2-digit", minute: "2-digit", second: "2-digit" });

  const money = (n) => Number(n).toLocaleString("ru-RU") + " ₽";
  const moneyUsd = (n) =>
    Number(n).toLocaleString("en-US", { maximumFractionDigits: 2 }) + " USDT";

  function optionText(groups, category) {
    const group = (groups || []).find((item) => item.category === category);
    return group && group.options ? group.options.join(", ") : "";
  }

  function normalizeStrategy(raw, fallback) {
    const d = raw && raw.data ? raw.data : raw || {};
    const tariff = (d.autoFollowingTariffDetails && d.autoFollowingTariffDetails[0]) || {};
    const base = fallback || {};
    return {
      id: d.id || base.id,
      title: d.title || base.title,
      author: d.author || base.author,
      createdAt: d.createdAt || base.createdAt,
      url: d.url || base.url,
      riskLevel: optionText(d.optionGroups, "RiskLevelRequirements") || base.riskLevel,
      riskCategory: optionText(d.optionGroups, "RiskCategoryRequirements") || base.riskCategory,
      minSum: d.minSum != null ? d.minSum : base.minSum,
      moneyLimit: d.moneyLimit != null ? d.moneyLimit : base.moneyLimit,
      tariffDesc: tariff.description || d.autoFollowingTariff || base.tariffDesc,
      tradeActivityIndex:
        d.tradeActivityIndex != null ? d.tradeActivityIndex : base.tradeActivityIndex,
      profit7Days: d.profit7Days != null ? d.profit7Days : base.profit7Days,
      profit30Days: d.profit30Days != null ? d.profit30Days : base.profit30Days,
      profit90Days: d.profit90Days != null ? d.profit90Days : base.profit90Days,
      profitLifetime: d.profitLifetime != null ? d.profitLifetime : base.profitLifetime,
      structure: Array.isArray(d.structure) && d.structure.length ? d.structure : base.structure || [],
    };
  }

  function normalizeEquity(raw) {
    const rows = raw && raw.data ? raw.data : raw;
    return (Array.isArray(rows) ? rows : [])
      .map((point) => ({ date: point.date, value: Number(point.value) }))
      .filter((point) => point.date && Number.isFinite(point.value))
      .sort((a, b) => a.date.localeCompare(b.date));
  }

  function positionText(strategy) {
    const fut = (strategy.structure || []).find((item) => item.id === "Fut");
    const cash = (strategy.structure || []).find((item) => item.id === "Money");
    const parts = [];
    if (fut) {
      if (fut.value < -0.05) parts.push("шорт фьючерса " + fmtPct(fut.value));
      else if (fut.value > 0.05) parts.push("лонг фьючерса " + fmtPct(fut.value));
      else parts.push("фьючерс почти закрыт");
    }
    if (cash) parts.push("кэш " + fmtPct(cash.value));
    return parts.join(", ") || "—";
  }

  function setStamp(mode, text) {
    const stamp = $("#parsed-stamp");
    if (!stamp) return;
    stamp.classList.remove("is-live", "is-cache", "is-loading");
    stamp.classList.add("is-" + mode);
    stamp.textContent = text;
  }

  function fillCase() {
    const strategy = state.strategy;
    if (!strategy) return;

    $("#hero-pnl").textContent = fmtPct(strategy.profitLifetime);
    $("#hero-m30").textContent = fmtPct(strategy.profit30Days);
    if ($("#hero-m90")) $("#hero-m90").textContent = fmtPct(strategy.profit90Days);
    $("#hero-minsum").textContent = money(strategy.minSum);
    if ($("#hero-title")) $("#hero-title").textContent = strategy.title;
    $("#case-title").textContent = strategy.title;
    $("#case-start").textContent = fmtDate(strategy.createdAt);
    $("#case-link").href = strategy.url;
    $("#spec-risk").textContent = strategy.riskLevel;
    $("#spec-cat").textContent = strategy.riskCategory;
    $("#spec-tariff").textContent = strategy.tariffDesc;
    $("#spec-ita").textContent = Number(strategy.tradeActivityIndex).toFixed(2);
    if ($("#spec-limit")) {
      $("#spec-limit").textContent = "до " + Number(strategy.moneyLimit).toLocaleString("ru-RU") + " ₽";
    }
    if ($("#spec-position")) $("#spec-position").textContent = positionText(strategy);

    const when = state.fetchedAt ? fmtTime(state.fetchedAt) : fmtDate(window.COMON_SOURCE.parsedAt);
    if (state.source === "live") setStamp("live", "Live с Comon · " + when);
    else if (state.source === "cache") setStamp("cache", "Кэш Comon · " + when);
    else setStamp("cache", "Офлайн-данные · " + when);

    const metrics = [
      ["За всё время", fmtPct(strategy.profitLifetime), "pos"],
      ["30 дней", fmtPct(strategy.profit30Days), strategy.profit30Days >= 0 ? "pos" : "neg"],
      ["7 дней", fmtPct(strategy.profit7Days), strategy.profit7Days >= 0 ? "pos" : "neg"],
      ["90 дней", fmtPct(strategy.profit90Days), strategy.profit90Days >= 0 ? "pos" : "neg"],
      ["Мин. сумма", money(strategy.minSum), ""],
      ["Запуск", fmtDate(strategy.createdAt), ""],
    ];

    $("#case-metrics").innerHTML = metrics
      .map(
        ([label, value, tone]) =>
          `<article class="glass metric"><span>${label}</span><b class="${tone}">${value}</b></article>`
      )
      .join("");

    $("#structure-bars").innerHTML = (strategy.structure || [])
      .map((item) => {
        const width = Math.min(100, Math.abs(item.value));
        const color = item.value >= 0 ? "var(--accent)" : "var(--neg)";
        return `
          <div>
            <div class="bar-label"><span>${item.name}</span><strong>${fmtPct(item.value)}</strong></div>
            <div class="bar-track"><div class="bar-fill" style="width:${width}%;background:${color}"></div></div>
          </div>`;
      })
      .join("");
  }

  function bybitPositionText(strategy) {
    const pos = strategy.position || {};
    if (!pos.size) return "позиции нет";
    const side = pos.side === "Sell" ? "шорт" : "лонг";
    return side + " " + pos.size + " BTC";
  }

  function fillBybit() {
    const strategy = bybit.strategy;
    if (!strategy || !$("#bybit-title")) return;
    $("#bybit-title").textContent = strategy.title;
    $("#bybit-equity").textContent = moneyUsd(strategy.equity);
    $("#bybit-position").textContent = bybitPositionText(strategy);
    $("#bybit-avg").textContent = strategy.position && strategy.position.avgPrice
      ? strategy.position.avgPrice.toLocaleString("en-US") + " USDT"
      : "—";
    $("#bybit-upl").textContent = (strategy.unrealized >= 0 ? "+" : "") + Number(strategy.unrealized).toFixed(2) + " USDT";
    $("#bybit-upl").className = strategy.unrealized >= 0 ? "pos" : "neg";
    $("#bybit-free").textContent = moneyUsd(strategy.available);

    const stamp = $("#bybit-stamp");
    const when = bybit.fetchedAt ? fmtTime(bybit.fetchedAt) : "";
    stamp.classList.remove("is-live", "is-cache", "is-loading");
    if (bybit.source === "live") {
      stamp.classList.add("is-live");
      stamp.textContent = "Live с Bybit · " + when;
    } else {
      stamp.classList.add("is-cache");
      stamp.textContent = "Кэш Bybit · " + when;
    }

    const metrics = [
      ["За всё время", fmtPct(strategy.profitLifetime), strategy.profitLifetime >= 0 ? "pos" : "neg"],
      ["30 дней", fmtPct(strategy.profit30Days), strategy.profit30Days >= 0 ? "pos" : "neg"],
      ["7 дней", fmtPct(strategy.profit7Days), strategy.profit7Days >= 0 ? "pos" : "neg"],
      ["Баланс", moneyUsd(strategy.equity), ""],
      ["Нереализ. PnL", (strategy.unrealized >= 0 ? "+" : "") + Number(strategy.unrealized).toFixed(2), strategy.unrealized >= 0 ? "pos" : "neg"],
      ["Позиция", bybitPositionText(strategy), ""],
    ];
    $("#bybit-metrics").innerHTML = metrics
      .map(
        ([label, value, tone]) =>
          `<article class="glass metric"><span>${label}</span><b class="${tone}">${value}</b></article>`
      )
      .join("");

    const notional = (strategy.position && strategy.position.size * strategy.position.avgPrice) || 0;
    const cash = Math.max(0, strategy.equity - notional);
    const rows = [
      { name: "USDT на счёте", value: strategy.equity ? (cash / strategy.equity) * 100 : 0 },
      { name: "Позиция BTC", value: strategy.equity ? (notional / strategy.equity) * 100 : 0 },
    ];
    $("#bybit-bars").innerHTML = rows
      .map((item) => {
        const width = Math.min(100, Math.abs(item.value));
        return `
          <div>
            <div class="bar-label"><span>${item.name}</span><strong>${fmtPct(item.value)}</strong></div>
            <div class="bar-track"><div class="bar-fill" style="width:${width}%;background:var(--accent)"></div></div>
          </div>`;
      })
      .join("");
  }

  function sliceRange(range) {
    if (range === "all") return state.equity;
    return state.equity.slice(-Number(range));
  }

  function drawLine(canvas, points, options) {
    const ctx = canvas.getContext("2d");
    const dpr = window.devicePixelRatio || 1;
    const parentW = canvas.parentElement ? canvas.parentElement.clientWidth : 0;
    const cssW = Math.max(0, parentW || canvas.clientWidth || 0);
    const cssH = canvas.clientHeight || 260;
    if (cssW < 40) {
      return { pad: options.pad || { t: 18, r: 16, b: 28, l: 44 } };
    }
    canvas.width = Math.floor(cssW * dpr);
    canvas.height = Math.floor(cssH * dpr);
    ctx.scale(dpr, dpr);

    if (!points.length) {
      ctx.clearRect(0, 0, cssW, cssH);
      return { pad: options.pad || { t: 18, r: 16, b: 28, l: 44 } };
    }

    const pad = options.pad || { t: 18, r: 16, b: 28, l: 44 };
    const w = cssW - pad.l - pad.r;
    const h = cssH - pad.t - pad.b;
    const values = points.map((p) => p.value);
    const min = Math.min(...values, 0);
    const max = Math.max(...values, 0);
    const span = max - min || 1;
    const xAt = (i) => pad.l + (i / Math.max(points.length - 1, 1)) * w;
    const yAt = (v) => pad.t + ((max - v) / span) * h;

    ctx.clearRect(0, 0, cssW, cssH);

    if (options.grid) {
      ctx.strokeStyle = "rgba(232,237,245,0.08)";
      ctx.lineWidth = 1;
      ctx.font = "11px IBM Plex Mono, monospace";
      ctx.fillStyle = "#8d97ab";
      for (let i = 0; i <= 4; i++) {
        const v = max - (span * i) / 4;
        const y = yAt(v);
        ctx.beginPath();
        ctx.moveTo(pad.l, y);
        ctx.lineTo(cssW - pad.r, y);
        ctx.stroke();
        ctx.fillText(fmtPct(v, 0), 8, y + 4);
      }
    }

    const path = new Path2D();
    points.forEach((p, i) => {
      const x = xAt(i);
      const y = yAt(p.value);
      if (i === 0) path.moveTo(x, y);
      else path.lineTo(x, y);
    });

    const fill = new Path2D(path);
    fill.lineTo(xAt(points.length - 1), pad.t + h);
    fill.lineTo(xAt(0), pad.t + h);
    fill.closePath();

    const last = points[points.length - 1].value;
    const grad = ctx.createLinearGradient(0, pad.t, 0, pad.t + h);
    const top = last >= 0 ? "rgba(61,255,164,0.28)" : "rgba(255,107,134,0.22)";
    grad.addColorStop(0, top);
    grad.addColorStop(1, "rgba(61,255,164,0)");
    ctx.fillStyle = grad;
    ctx.fill(fill);

    ctx.strokeStyle = last >= 0 ? "#3dffa4" : "#ff6b86";
    ctx.lineWidth = options.lineWidth || 2.2;
    ctx.lineJoin = "round";
    ctx.lineCap = "round";
    ctx.stroke(path);

    const lx = xAt(points.length - 1);
    const ly = yAt(last);
    const pulse = (Math.sin((performance.now() || 0) / 260) + 1) / 2;
    ctx.beginPath();
    ctx.arc(lx, ly, 8 + pulse * 6, 0, Math.PI * 2);
    ctx.strokeStyle = last >= 0 ? "rgba(61,255,164," + (0.18 + pulse * 0.22) + ")" : "rgba(255,107,134,0.28)";
    ctx.lineWidth = 2;
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(lx, ly, 3.5, 0, Math.PI * 2);
    ctx.fillStyle = last >= 0 ? "#3dffa4" : "#ff6b86";
    ctx.fill();

    if (options.zero) {
      ctx.strokeStyle = "rgba(232,237,245,0.2)";
      ctx.setLineDash([4, 4]);
      ctx.beginPath();
      ctx.moveTo(pad.l, yAt(0));
      ctx.lineTo(cssW - pad.r, yAt(0));
      ctx.stroke();
      ctx.setLineDash([]);
    }

    return { xAt, yAt, pad, cssW, cssH };
  }

  function mountCharts() {
    const hero = $("#hero-spark");
    const chart = $("#equity-chart");
    const tip = $("#chart-tip");
    let range = "all";
    let geometry = null;
    let view = state.equity;

    const paint = () => {
      drawLine(hero, state.equity, { pad: { t: 12, r: 8, b: 10, l: 8 }, lineWidth: 2 });
      view = sliceRange(range);
      geometry = drawLine(chart, view, {
        pad: { t: 16, r: 14, b: 26, l: 48 },
        grid: true,
        zero: true,
        lineWidth: 2.4,
      });
    };

    paint();
    window.addEventListener("resize", paint);

    $$("#comon-pills .pill").forEach((btn) => {
      btn.addEventListener("click", () => {
        $$("#comon-pills .pill").forEach((b) => b.classList.remove("is-active"));
        btn.classList.add("is-active");
        range = btn.dataset.range;
        paint();
      });
    });

    chart.addEventListener("mousemove", (event) => {
      if (!geometry || !view.length) return;
      const rect = chart.getBoundingClientRect();
      const x = event.clientX - rect.left;
      const ratio = (x - geometry.pad.l) / (rect.width - geometry.pad.l - geometry.pad.r);
      const index = Math.min(view.length - 1, Math.max(0, Math.round(ratio * (view.length - 1))));
      const point = view[index];
      tip.hidden = false;
      tip.style.left = event.clientX - rect.left + "px";
      tip.style.top = event.clientY - rect.top + "px";
      tip.innerHTML = `<strong>${fmtDate(point.date)}</strong><br>${fmtPct(point.value, 2)}`;
    });

    chart.addEventListener("mouseleave", () => {
      tip.hidden = true;
    });

    return { refresh: paint };
  }

  function mountBybitChart() {
    const chart = $("#bybit-chart");
    const tip = $("#bybit-tip");
    if (!chart) return { refresh: () => {} };
    let range = "all";
    let geometry = null;
    let view = bybit.equity;

    const paint = () => {
      const points = range === "all" ? bybit.equity : bybit.equity.slice(-Number(range));
      view = points;
      geometry = drawLine(chart, view, {
        pad: { t: 16, r: 14, b: 26, l: 48 },
        grid: true,
        zero: true,
        lineWidth: 2.4,
      });
    };

    paint();
    window.addEventListener("resize", paint);
    $$("#bybit-pills .pill").forEach((btn) => {
      btn.addEventListener("click", () => {
        $$("#bybit-pills .pill").forEach((b) => b.classList.remove("is-active"));
        btn.classList.add("is-active");
        range = btn.dataset.range;
        paint();
      });
    });
    chart.addEventListener("mousemove", (event) => {
      if (!geometry || !view.length) return;
      const rect = chart.getBoundingClientRect();
      const x = event.clientX - rect.left;
      const ratio = (x - geometry.pad.l) / (rect.width - geometry.pad.l - geometry.pad.r);
      const index = Math.min(view.length - 1, Math.max(0, Math.round(ratio * (view.length - 1))));
      const point = view[index];
      tip.hidden = false;
      tip.style.left = event.clientX - rect.left + "px";
      tip.style.top = event.clientY - rect.top + "px";
      const extra = point.balance != null ? "<br>" + moneyUsd(point.balance) : "";
      tip.innerHTML = `<strong>${fmtDate(point.date)}</strong><br>${fmtPct(point.value, 2)}${extra}`;
    });
    chart.addEventListener("mouseleave", () => {
      tip.hidden = true;
    });
    return { refresh: paint };
  }

  async function fetchJson(url) {
    const res = await fetch(url, {
      headers: { Accept: "application/json" },
      cache: "no-store",
    });
    if (!res.ok) throw new Error(url + " " + res.status);
    const json = await res.json();
    return { json, source: res.headers.get("X-Data-Source") || "file" };
  }

  async function loadLive(silent) {
    if (loadingLive) return;
    loadingLive = true;
    if (!silent || !state.equity.length) {
      setStamp("loading", "Тяну данные с Comon…");
    }
    try {
    const pairs = [
      {
        strategy: "api/comon.php?id=" + STRATEGY_ID,
        profit: "api/comon.php?id=" + STRATEGY_ID + "&kind=profit",
        prefer: "live",
      },
      {
        strategy: "/api/comon/" + STRATEGY_ID,
        profit: "/api/comon/" + STRATEGY_ID + "/profit",
        prefer: "live",
      },
      {
        strategy: "data/strategy-" + STRATEGY_ID + ".json",
        profit: "data/strategy-" + STRATEGY_ID + "-profit.json",
        prefer: "cache",
      },
    ];

    for (const pair of pairs) {
      try {
        const [strategyRes, profitRes] = await Promise.all([
          fetchJson(pair.strategy),
          fetchJson(pair.profit),
        ]);
        const equity = normalizeEquity(profitRes.json);
        if (!equity.length) throw new Error("empty equity");
        const sourceHeader = strategyRes.source;
        state.strategy = normalizeStrategy(strategyRes.json, state.strategy);
        state.equity = equity;
        state.source = sourceHeader === "comon-live" ? "live" : sourceHeader === "cache" ? "cache" : pair.prefer;
        state.fetchedAt = new Date();
        fillCase();
        if (window.__charts) window.__charts.refresh();
        return state.source;
      } catch (error) {
        console.warn("Comon load failed", pair.strategy, error);
      }
    }

    state.source = "fallback";
    fillCase();
    if (window.__charts) window.__charts.refresh();
    return "fallback";
    } finally {
      loadingLive = false;
    }
  }

  async function loadBybit(silent) {
    if (loadingBybit) return;
    loadingBybit = true;
    const stamp = $("#bybit-stamp");
    if (stamp && (!silent || !bybit.equity.length)) {
      stamp.classList.remove("is-live", "is-cache");
      stamp.classList.add("is-loading");
      stamp.textContent = "Тяну баланс с Bybit…";
    }
    try {
    const urls = ["api/bybit.php", "/api/bybit/case", "data/bybit-case.json"];
    for (const url of urls) {
      try {
        const res = await fetchJson(url);
        const payload = res.json;
        if (!payload || !payload.strategy || !payload.series) throw new Error("bad bybit payload");
        bybit.strategy = payload.strategy;
        bybit.equity = payload.series;
        bybit.source = res.source === "bybit-live" || url.indexOf("/api/") === 0 ? "live" : "cache";
        if (res.source === "cache") bybit.source = "cache";
        bybit.fetchedAt = new Date();
        fillBybit();
        if (window.__bybitChart) window.__bybitChart.refresh();
        return bybit.source;
      } catch (error) {
        console.warn("Bybit load failed", url, error);
      }
    }
    if (stamp && !silent) {
      stamp.classList.remove("is-loading");
      stamp.classList.add("is-cache");
      stamp.textContent = "Bybit недоступен";
    }
    return "fallback";
    } finally {
      loadingBybit = false;
    }
  }

  function mountNav() {
    const burger = $("#burger");
    const nav = $("#nav");
    burger.addEventListener("click", () => nav.classList.toggle("is-open"));
    $$("#nav a").forEach((link) =>
      link.addEventListener("click", () => nav.classList.remove("is-open"))
    );
  }

  function mountForm() {
    const form = $("#lead-form");
    const note = $("#form-note");
    if (!form || !note) return;
    if (new URLSearchParams(location.search).get("sent") === "1") {
      note.hidden = false;
      note.textContent = "Заявка сохранена. Напишите в Telegram — так быстрее всего ответить.";
    }
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const data = new FormData(form);
      if ((data.get("website") || "").toString().trim()) {
        return;
      }
      const text = [
        "Заявка AM QuantLab",
        "Имя: " + data.get("name"),
        "Контакт: " + data.get("contact"),
        "Рынок: " + data.get("market"),
        "Задача: " + data.get("message"),
      ].join("\n");
      note.hidden = false;
      note.textContent = "Отправляем заявку…";
      try {
        const res = await fetch("/api/lead.php", {
          method: "POST",
          headers: {
            Accept: "application/json",
            "X-Requested-With": "fetch",
          },
          body: data,
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || json.ok === false) {
          throw new Error(json.error || json.message || "Не удалось сохранить заявку");
        }
        note.textContent = "Заявка сохранена. Напишите в Telegram — так быстрее всего ответить.";
      } catch (err) {
        note.textContent = (err && err.message) || "Не удалось сохранить заявку. Напишите в Telegram.";
      }
      window.open(
        "https://t.me/where_is_Lebowskis_money?text=" + encodeURIComponent(text),
        "_blank",
        "noopener"
      );
      form.reset();
    });
  }

  function mountSlider() {
    const track = $("#case-track");
    const dots = $$(".slider-dot");
    if (!track || !dots.length) return;
    let index = 0;
    const max = dots.length - 1;

    const go = (next) => {
      index = Math.max(0, Math.min(max, next));
      track.style.transform = "translateX(-" + index * 100 + "%)";
      dots.forEach((dot) => dot.classList.toggle("is-active", Number(dot.dataset.slide) === index));
      window.setTimeout(() => {
        if (window.__charts) window.__charts.refresh();
        if (window.__bybitChart) window.__bybitChart.refresh();
      }, 460);
    };

    $("#slide-prev").addEventListener("click", () => go(index - 1));
    $("#slide-next").addEventListener("click", () => go(index + 1));
    dots.forEach((dot) => {
      dot.addEventListener("click", () => go(Number(dot.dataset.slide)));
    });

    let startX = 0;
    track.addEventListener("touchstart", (event) => {
      startX = event.changedTouches[0].clientX;
    }, { passive: true });
    track.addEventListener("touchend", (event) => {
      const dx = event.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 40) go(index + (dx < 0 ? 1 : -1));
    }, { passive: true });
  }

  fillCase();
  window.__charts = mountCharts();
  window.__bybitChart = mountBybitChart();
  mountSlider();
  mountNav();
  mountForm();
  loadLive();
  loadBybit();

  $("#parsed-stamp").addEventListener("click", () => {
    loadLive();
  });
  if ($("#bybit-stamp")) {
    $("#bybit-stamp").addEventListener("click", () => {
      loadBybit();
    });
  }

  window.setInterval(() => {
    loadLive(true);
    loadBybit(true);
  }, REFRESH_MS);
  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) {
      loadLive(true);
      loadBybit(true);
    }
  });

  const pulseCharts = () => {
    if (!document.hidden) {
      if (window.__charts) window.__charts.refresh();
      if (window.__bybitChart) window.__bybitChart.refresh();
    }
    window.setTimeout(() => requestAnimationFrame(pulseCharts), 90);
  };
  requestAnimationFrame(pulseCharts);
})();
