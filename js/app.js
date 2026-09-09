(function () {
  const REFRESH_MS = 20 * 1000;
  const stores = {};
  const charts = [];
  let loadingBybit = false;
  let loadingTinkoff = false;
  const loadingComon = {};

  const bybit = {
    strategy: null,
    equity: [],
    source: "fallback",
    fetchedAt: null,
  };

  const tinkoff = {
    strategy: null,
    equity: [],
    source: "fallback",
    fetchedAt: null,
  };

  const $ = (sel, root = document) => (root || document).querySelector(sel);
  const $$ = (sel, root = document) => [...(root || document).querySelectorAll(sel)];

  function storeFor(slide) {
    const slug = slide.dataset.slug;
    if (!stores[slug]) {
      stores[slug] = { strategy: null, equity: [], source: "fallback", fetchedAt: null };
    }
    return stores[slug];
  }

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

  function setStamp(el, mode, text) {
    if (!el) return;
    el.classList.remove("is-live", "is-cache", "is-loading");
    el.classList.add("is-" + mode);
    el.textContent = text;
  }

  function fillHero(strategy, equity) {
    if (!strategy) return;
    if ($("#hero-pnl")) $("#hero-pnl").textContent = fmtPct(strategy.profitLifetime);
    if ($("#hero-m30")) $("#hero-m30").textContent = fmtPct(strategy.profit30Days);
    if ($("#hero-m90")) $("#hero-m90").textContent = fmtPct(strategy.profit90Days);
    if ($("#hero-minsum") && strategy.minSum != null) $("#hero-minsum").textContent = money(strategy.minSum);
    if ($("#hero-title")) $("#hero-title").textContent = strategy.title;
    const hero = $("#hero-spark");
    if (hero && equity && equity.length) {
      drawLine(hero, equity, { pad: { t: 12, r: 8, b: 10, l: 8 }, lineWidth: 2 });
    }
  }

  function fillComonSlide(slide, data) {
    const strategy = data.strategy;
    if (!strategy) return;
    const q = (sel) => slide.querySelector(sel);
    const title = q(".js-title");
    if (title) title.textContent = strategy.title;
    const start = q(".js-start");
    if (start) start.textContent = fmtDate(strategy.createdAt);
    const link = q(".js-link");
    if (link && strategy.url) {
      link.href = strategy.url;
    }
    const risk = q(".js-risk");
    if (risk) risk.textContent = strategy.riskLevel || "—";
    const cat = q(".js-cat");
    if (cat) cat.textContent = strategy.riskCategory || "—";
    const tariff = q(".js-tariff");
    if (tariff) tariff.textContent = strategy.tariffDesc || "—";
    const ita = q(".js-ita");
    if (ita) ita.textContent = Number(strategy.tradeActivityIndex || 0).toFixed(2);
    const limit = q(".js-limit");
    if (limit && strategy.moneyLimit) {
      limit.textContent = "до " + Number(strategy.moneyLimit).toLocaleString("ru-RU") + " ₽";
    }
    const pos = q(".js-position");
    if (pos) pos.textContent = positionText(strategy);

    const when = data.fetchedAt ? fmtTime(data.fetchedAt) : "";
    if (data.source === "live") setStamp(q(".js-stamp"), "live", "Live с Comon · " + when);
    else if (data.source === "cache") setStamp(q(".js-stamp"), "cache", "Кэш Comon · " + when);
    else setStamp(q(".js-stamp"), "cache", "Офлайн-данные");

    const metrics = [
      ["За всё время", fmtPct(strategy.profitLifetime), "pos"],
      ["30 дней", fmtPct(strategy.profit30Days), strategy.profit30Days >= 0 ? "pos" : "neg"],
      ["7 дней", fmtPct(strategy.profit7Days), strategy.profit7Days >= 0 ? "pos" : "neg"],
      ["90 дней", fmtPct(strategy.profit90Days), strategy.profit90Days >= 0 ? "pos" : "neg"],
      ["Мин. сумма", money(strategy.minSum || 0), ""],
      ["Запуск", fmtDate(strategy.createdAt), ""],
    ];
    const box = q(".js-metrics");
    if (box) {
      box.innerHTML = metrics
        .map(
          ([label, value, tone]) =>
            `<article class="glass metric"><span>${label}</span><b class="${tone}">${value}</b></article>`
        )
        .join("");
    }
    const bars = q(".js-bars");
    if (bars) {
      bars.innerHTML = (strategy.structure || [])
        .map((item) => {
          const width = Math.min(100, Math.abs(item.value));
          const color = item.value >= 0 ? "var(--accent)" : "var(--neg)";
          return `<div>
            <div class="bar-label"><span>${item.name}</span><strong>${fmtPct(item.value)}</strong></div>
            <div class="bar-track"><div class="bar-fill" style="width:${width}%;background:${color}"></div></div>
          </div>`;
        })
        .join("");
    }
    if (slide.dataset.hero === "1") fillHero(strategy, data.equity);
  }

  function bybitPositionText(strategy, instrument) {
    const pos = strategy.position || {};
    if (!pos.size) return "позиции нет";
    const side = pos.side === "Sell" ? "шорт" : "лонг";
    const unit = instrument && instrument.indexOf("BTC") === 0 ? "BTC" : instrument || "";
    return side + " " + pos.size + (unit ? " " + unit : "");
  }

  function fillBybitSlide(slide) {
    const strategy = bybit.strategy;
    if (!strategy) return;
    const q = (sel) => slide.querySelector(sel);
    const instrument = slide.dataset.instrument || "BTCUSDT";
    const equity = q(".js-equity");
    if (equity) equity.textContent = moneyUsd(strategy.equity);
    const pos = q(".js-position");
    if (pos) pos.textContent = bybitPositionText(strategy, instrument);
    const avg = q(".js-avg");
    if (avg) {
      avg.textContent = strategy.position && strategy.position.avgPrice
        ? strategy.position.avgPrice.toLocaleString("en-US") + " USDT"
        : "—";
    }
    const upl = q(".js-upl");
    if (upl) {
      upl.textContent = (strategy.unrealized >= 0 ? "+" : "") + Number(strategy.unrealized).toFixed(2) + " USDT";
      upl.className = "js-upl " + (strategy.unrealized >= 0 ? "pos" : "neg");
    }
    const free = q(".js-free");
    if (free) free.textContent = moneyUsd(strategy.available);

    const when = bybit.fetchedAt ? fmtTime(bybit.fetchedAt) : "";
    if (bybit.source === "live") setStamp(q(".js-stamp"), "live", "Live с Bybit · " + when);
    else setStamp(q(".js-stamp"), "cache", "Кэш Bybit · " + when);

    const metrics = [
      ["За всё время", fmtPct(strategy.profitLifetime), strategy.profitLifetime >= 0 ? "pos" : "neg"],
      ["30 дней", fmtPct(strategy.profit30Days), strategy.profit30Days >= 0 ? "pos" : "neg"],
      ["7 дней", fmtPct(strategy.profit7Days), strategy.profit7Days >= 0 ? "pos" : "neg"],
      ["Баланс", moneyUsd(strategy.equity), ""],
      ["Нереализ. PnL", (strategy.unrealized >= 0 ? "+" : "") + Number(strategy.unrealized).toFixed(2), strategy.unrealized >= 0 ? "pos" : "neg"],
      ["Позиция", bybitPositionText(strategy, instrument), ""],
    ];
    const box = q(".js-metrics");
    if (box) {
      box.innerHTML = metrics
        .map(
          ([label, value, tone]) =>
            `<article class="glass metric"><span>${label}</span><b class="${tone}">${value}</b></article>`
        )
        .join("");
    }
    const notional = (strategy.position && strategy.position.size * strategy.position.avgPrice) || 0;
    const cash = Math.max(0, strategy.equity - notional);
    const rows = [
      { name: "USDT на счёте", value: strategy.equity ? (cash / strategy.equity) * 100 : 0 },
      { name: "Позиция " + instrument, value: strategy.equity ? (notional / strategy.equity) * 100 : 0 },
    ];
    const bars = q(".js-bars");
    if (bars) {
      bars.innerHTML = rows
        .map((item) => {
          const width = Math.min(100, Math.abs(item.value));
          return `<div>
            <div class="bar-label"><span>${item.name}</span><strong>${fmtPct(item.value)}</strong></div>
            <div class="bar-track"><div class="bar-fill" style="width:${width}%;background:var(--accent)"></div></div>
          </div>`;
        })
        .join("");
    }
  }

  function tinkoffPositionText(strategy) {
    const pos = strategy.position || {};
    const ticker = pos.ticker || strategy.instrument || "CNY";
    if (!pos.size) return "позиции нет";
    const side = pos.side === "Sell" ? "шорт" : "лонг";
    return side + " " + pos.size + " " + ticker;
  }

  function fillTinkoff() {
    const strategy = tinkoff.strategy;
    if (!strategy || !$("#tinkoff-title")) return;
    $("#tinkoff-title").textContent = strategy.title;
  }

  function canvasCssSize(canvas) {
    const stage = canvas.closest(".chart-stage");
    if (stage) {
      return {
        cssW: Math.floor(stage.clientWidth || 0),
        cssH: Math.floor(stage.clientHeight || 280),
      };
    }
    const parent = canvas.parentElement;
    return {
      cssW: Math.floor(canvas.clientWidth || (parent && parent.clientWidth) || 0),
      cssH: Math.floor(parseFloat(getComputedStyle(canvas).height) || canvas.clientHeight || 180),
    };
  }

  function drawLine(canvas, points, options) {
    if (!canvas) return { pad: options.pad || { t: 18, r: 16, b: 28, l: 44 } };
    const ctx = canvas.getContext("2d");
    const dpr = window.devicePixelRatio || 1;
    const size = canvasCssSize(canvas);
    const cssW = Math.max(0, size.cssW);
    const cssH = Math.max(0, size.cssH);
    if (cssW < 40 || cssH < 40) {
      return { pad: options.pad || { t: 18, r: 16, b: 28, l: 44 } };
    }
    if (!canvas.closest(".chart-stage")) {
      canvas.style.width = "100%";
      canvas.style.height = cssH + "px";
    }
    const bw = Math.floor(cssW * dpr);
    const bh = Math.floor(cssH * dpr);
    if (canvas.width !== bw || canvas.height !== bh) {
      canvas.width = bw;
      canvas.height = bh;
    }
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
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

  function mountSlideChart(slide, getPoints, tipMoney) {
    const chart = slide.querySelector(".js-chart");
    const tip = slide.querySelector(".js-tip");
    if (!chart) return { refresh: () => {} };
    let range = "all";
    let geometry = null;
    let view = [];
    const paint = () => {
      const all = getPoints() || [];
      view = range === "all" ? all : all.slice(-Number(range));
      geometry = drawLine(chart, view, {
        pad: { t: 16, r: 14, b: 26, l: 48 },
        grid: true,
        zero: true,
        lineWidth: 2.4,
      });
    };
    paint();
    window.addEventListener("resize", paint);
    if (window.ResizeObserver && chart.parentElement) {
      new ResizeObserver(paint).observe(chart.parentElement);
    }
    $$( ".pill", slide.querySelector(".js-pills") || slide).forEach((btn) => {
      btn.addEventListener("click", () => {
        $$(".pill", slide.querySelector(".js-pills") || slide).forEach((b) => b.classList.remove("is-active"));
        btn.classList.add("is-active");
        range = btn.dataset.range;
        paint();
      });
    });
    if (tip) {
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
        let extra = "";
        if (point.balance != null) extra = "<br>" + (tipMoney === "usd" ? moneyUsd(point.balance) : money(point.balance));
        tip.innerHTML = `<strong>${fmtDate(point.date)}</strong><br>${fmtPct(point.value, 2)}${extra}`;
      });
      chart.addEventListener("mouseleave", () => {
        tip.hidden = true;
      });
    }
    return { refresh: paint };
  }

  async function fetchJson(url) {
    const res = await fetch(url, {
      headers: { Accept: "application/json" },
      cache: "no-store",
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw new Error(json.detail || json.error || url + " " + res.status);
    }
    return { json, source: res.headers.get("X-Data-Source") || "file" };
  }

  async function loadComon(slide, silent) {
    const id = slide.dataset.comonId;
    const slug = slide.dataset.slug;
    if (!id || loadingComon[slug]) return;
    loadingComon[slug] = true;
    const data = storeFor(slide);
    const stamp = slide.querySelector(".js-stamp");
    if (!silent || !data.equity.length) setStamp(stamp, "loading", "Обновить с Comon");
    try {
      const [strategyRes, profitRes] = await Promise.all([
        fetchJson("/api/comon.php?id=" + id + "&t=" + Date.now()),
        fetchJson("/api/comon.php?id=" + id + "&kind=profit&t=" + Date.now()),
      ]);
      const equity = normalizeEquity(profitRes.json);
      if (!equity.length) throw new Error("empty equity");
      data.strategy = normalizeStrategy(strategyRes.json, data.strategy);
      data.equity = equity;
      data.source = strategyRes.source === "comon-live" ? "live" : strategyRes.source === "cache" ? "cache" : "live";
      data.fetchedAt = new Date();
      fillComonSlide(slide, data);
      charts.forEach((c) => c.refresh && c.refresh());
    } catch (error) {
      console.warn("Comon load failed", id, error);
      if (!silent) setStamp(stamp, "cache", "Comon недоступен");
      fillComonSlide(slide, data);
    } finally {
      loadingComon[slug] = false;
    }
  }

  async function loadBybit(silent) {
    const slides = $$('.case-slide[data-venue="bybit"]');
    if (!slides.length || loadingBybit) return;
    loadingBybit = true;
    slides.forEach((slide) => {
      const stamp = slide.querySelector(".js-stamp");
      if (stamp && (!silent || !bybit.equity.length)) setStamp(stamp, "loading", "Обновить с Bybit");
    });
    try {
      const res = await fetchJson("/api/bybit.php");
      const payload = res.json;
      if (!payload || !payload.strategy || !payload.series) throw new Error("bad bybit payload");
      bybit.strategy = payload.strategy;
      bybit.equity = payload.series;
      bybit.source = res.source === "cache" ? "cache" : "live";
      bybit.fetchedAt = new Date();
      slides.forEach((slide) => fillBybitSlide(slide));
      charts.forEach((c) => c.refresh && c.refresh());
    } catch (error) {
      console.warn("Bybit load failed", error);
      if (!silent) {
        slides.forEach((slide) => setStamp(slide.querySelector(".js-stamp"), "cache", "Bybit недоступен"));
      }
    } finally {
      loadingBybit = false;
    }
  }

  function tinkoffVisible() {
    const slide = $("#slide-tinkoff");
    return !!(slide && !slide.hidden);
  }

  async function loadTinkoff(silent) {
    if (!tinkoffVisible() || loadingTinkoff) return;
    loadingTinkoff = true;
    try {
      const res = await fetchJson("/api/tinkoff.php");
      const payload = res.json;
      if (!payload || !payload.strategy || !payload.series) throw new Error("bad tinkoff payload");
      tinkoff.strategy = payload.strategy;
      tinkoff.equity = payload.series;
      tinkoff.source = res.source === "cache" ? "cache" : "live";
      tinkoff.fetchedAt = new Date();
      fillTinkoff();
    } catch (error) {
      console.warn("Tinkoff load failed", error);
    } finally {
      loadingTinkoff = false;
    }
  }

  function mountForm() {
    const form = $("#lead-form");
    const note = $("#form-note");
    if (!form || !note) return;
    if (new URLSearchParams(location.search).get("sent") === "1") {
      note.hidden = false;
      note.textContent = "Скоро мы с вами свяжемся";
    }
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const data = new FormData(form);
      if ((data.get("website") || "").toString().trim()) {
        return;
      }
      const button = form.querySelector('button[type="submit"]');
      note.hidden = false;
      note.textContent = "Отправляем заявку…";
      if (button) button.disabled = true;
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
          throw new Error(json.error || json.message || "Не удалось отправить заявку");
        }
        note.textContent = "Скоро мы с вами свяжемся";
        form.reset();
      } catch (err) {
        note.textContent = (err && err.message) || "Не удалось отправить заявку. Попробуйте ещё раз.";
      } finally {
        if (button) button.disabled = false;
      }
    });
  }

  function mountReadyOrder() {
    const modal = $("#ready-order-modal");
    const form = $("#ready-order-form");
    if (!modal || !form) return;
    const titleEl = $("#ready-order-title");
    const priceEl = $("#ready-order-price");
    const slugInput = $("#ready-order-slug");
    const note = $("#ready-order-note");
    const open = (btn) => {
      if (slugInput) slugInput.value = btn.dataset.slug || "";
      if (titleEl) titleEl.textContent = btn.dataset.title || "";
      if (priceEl) priceEl.textContent = btn.dataset.price || "";
      if (note) {
        note.hidden = true;
        note.textContent = "";
      }
      form.reset();
      if (slugInput) slugInput.value = btn.dataset.slug || "";
      modal.hidden = false;
      document.body.classList.add("modal-open");
    };
    const close = () => {
      modal.hidden = true;
      document.body.classList.remove("modal-open");
    };
    $$("[data-ready-order]").forEach((btn) => {
      btn.addEventListener("click", () => open(btn));
    });
    $$("[data-ready-close]", modal).forEach((el) => {
      el.addEventListener("click", close);
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && !modal.hidden) close();
    });
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const data = new FormData(form);
      if ((data.get("website") || "").toString().trim()) {
        return;
      }
      const button = form.querySelector('button[type="submit"]');
      if (note) {
        note.hidden = false;
        note.textContent = "Отправляем заявку…";
      }
      if (button) button.disabled = true;
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
          throw new Error(json.error || json.message || "Не удалось оформить");
        }
        if (note) note.textContent = "Заявка ушла на почту. Скоро свяжемся.";
        form.reset();
        if (slugInput) slugInput.value = "";
      } catch (err) {
        if (note) note.textContent = (err && err.message) || "Не удалось оформить. Попробуйте ещё раз.";
      } finally {
        if (button) button.disabled = false;
      }
    });
  }

  function mountSlider() {
    const track = $("#case-track");
    const viewport = track && track.parentElement;
    const dots = $$(".slider-dot").filter((dot) => !dot.hidden);
    if (!track || !viewport || !dots.length) return;
    let index = 0;
    const max = dots.length - 1;
    const go = (next) => {
      index = Math.max(0, Math.min(max, next));
      track.style.transform = "translate3d(-" + index * 100 + "%,0,0)";
      dots.forEach((dot) => dot.classList.toggle("is-active", Number(dot.dataset.slide) === index));
      window.setTimeout(() => {
        charts.forEach((c) => c.refresh && c.refresh());
      }, 460);
    };
    const prev = $("#slide-prev");
    const next = $("#slide-next");
    if (prev) prev.addEventListener("click", () => go(index - 1));
    if (next) next.addEventListener("click", () => go(index + 1));
    dots.forEach((dot) => {
      dot.addEventListener("click", () => go(Number(dot.dataset.slide)));
    });
    let startX = 0;
    viewport.addEventListener("touchstart", (event) => {
      startX = event.changedTouches[0].clientX;
    }, { passive: true });
    viewport.addEventListener("touchend", (event) => {
      const dx = event.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 40) go(index + (dx < 0 ? 1 : -1));
    }, { passive: true });
    window.addEventListener("resize", () => go(index));
  }

  function bootCases() {
    $$('.case-slide[data-venue="comon"]').forEach((slide) => {
      const data = storeFor(slide);
      if (slide.dataset.comonId === "131208" && window.COMON_STRATEGY) {
        data.strategy = window.COMON_STRATEGY;
        data.equity = window.COMON_EQUITY || [];
        fillComonSlide(slide, data);
      }
      charts.push(mountSlideChart(slide, () => storeFor(slide).equity, false));
      const stamp = slide.querySelector(".js-stamp");
      if (stamp) stamp.addEventListener("click", () => loadComon(slide));
      loadComon(slide);
    });
    const bybitSlides = $$('.case-slide[data-venue="bybit"]');
    bybitSlides.forEach((slide) => {
      charts.push(mountSlideChart(slide, () => bybit.equity, "usd"));
      const stamp = slide.querySelector(".js-stamp");
      if (stamp) stamp.addEventListener("click", () => loadBybit());
    });
    if (bybitSlides.length) loadBybit();
  }

  mountSlider();
  mountForm();
  mountReadyOrder();
  try {
    bootCases();
  } catch (error) {
    console.warn("cases", error);
  }
  if (tinkoffVisible()) loadTinkoff();
  fetch("/api/boot.php", { cache: "no-store" }).catch(() => {});

  window.setInterval(() => {
    $$('.case-slide[data-venue="comon"]').forEach((slide) => loadComon(slide, true));
    loadBybit(true);
    if (tinkoffVisible()) loadTinkoff(true);
  }, REFRESH_MS);
  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) {
      $$('.case-slide[data-venue="comon"]').forEach((slide) => loadComon(slide, true));
      loadBybit(true);
      if (tinkoffVisible()) loadTinkoff(true);
    }
  });

  const pulseCharts = () => {
    if (!document.hidden) {
      charts.forEach((c) => c.refresh && c.refresh());
      const heroSlide = $('.case-slide[data-hero="1"]');
      if (heroSlide) {
        const st = storeFor(heroSlide);
        if (st.strategy) fillHero(st.strategy, st.equity);
      }
    }
    window.setTimeout(() => requestAnimationFrame(pulseCharts), 90);
  };
  requestAnimationFrame(pulseCharts);
})();
