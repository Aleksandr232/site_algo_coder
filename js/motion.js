(function () {
  const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => [...document.querySelectorAll(sel)];

  let nextId = 1;

  function rand(min, max) {
    return min + Math.random() * (max - min);
  }

  function makeCandle(price) {
    const drift = (Math.random() - 0.47) * rand(0.35, 1.6);
    const open = price;
    const close = Math.max(8, price + drift);
    const wick = rand(0.12, 0.85);
    return {
      id: nextId++,
      open,
      close,
      high: Math.max(open, close) + wick,
      low: Math.min(open, close) - wick,
      vol: rand(0.25, 1),
      signal: null,
      signalText: "",
      linkedId: null,
    };
  }

  function seedCandles(count, start) {
    const list = [];
    let price = start;
    for (let i = 0; i < count; i++) {
      const candle = makeCandle(price);
      price = candle.close;
      list.push(candle);
    }
    return list;
  }

  function seedTrades(candles) {
    for (let i = 5; i < candles.length - 3; i += 8) {
      const side = candles[i].close >= candles[i - 1].close ? "buy" : "sell";
      candles[i].signal = side;
      candles[i].signalText = side === "buy" ? "Покупка" : "Продажа";
      const exit = candles[i + 4] || candles[i + 3];
      if (!exit) continue;
      const pnl =
        side === "buy"
          ? (exit.close - candles[i].close) / candles[i].close
          : (candles[i].close - exit.close) / candles[i].close;
      exit.signal = "tp";
      exit.linkedId = candles[i].id;
      exit.signalText = (pnl >= 0 ? "Профит +" : "Выход ") + Math.abs(pnl * 100).toFixed(1) + "%";
    }
  }

  function maybeTrade(state) {
    const last = state.candles[state.candles.length - 1];
    if (!last) return;

    if (!state.position) {
      state.idle += 1;
      if (state.idle < 4) return;
      const prev = state.candles[state.candles.length - 3] || last;
      const side = last.close >= prev.close ? "buy" : "sell";
      last.signal = side;
      last.signalText = side === "buy" ? "Покупка" : "Продажа";
      state.position = { side: side, entryId: last.id, entryPrice: last.close, bars: 0 };
      state.idle = 0;
      return;
    }

    state.position.bars += 1;
    const pos = state.position;
    const pnl =
      pos.side === "buy"
        ? (last.close - pos.entryPrice) / pos.entryPrice
        : (pos.entryPrice - last.close) / pos.entryPrice;
    if ((pnl > 0.01 && pos.bars >= 3) || pos.bars >= 7) {
      last.signal = "tp";
      last.linkedId = pos.entryId;
      last.signalText = (pnl >= 0 ? "Профит +" : "Выход ") + Math.abs(pnl * 100).toFixed(1) + "%";
      state.position = null;
    }
  }

  function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  function drawLabel(ctx, text, x, y, color) {
    ctx.font = "600 10px IBM Plex Mono, monospace";
    const width = ctx.measureText(text).width + 10;
    const left = Math.round(x - width / 2);
    const top = Math.round(y);
    ctx.fillStyle = "rgba(8, 12, 18, 0.45)";
    ctx.strokeStyle = color;
    ctx.lineWidth = 1;
    roundRect(ctx, left, top, width, 16, 8);
    ctx.fill();
    ctx.stroke();
    ctx.fillStyle = color;
    ctx.fillText(text, left + 5, top + 11);
  }

  function drawArrow(ctx, x, y, dir, color) {
    ctx.fillStyle = color;
    ctx.shadowColor = color;
    ctx.shadowBlur = 0;
    ctx.beginPath();
    if (dir === "up") {
      ctx.moveTo(x, y);
      ctx.lineTo(x - 5, y + 8);
      ctx.lineTo(x + 5, y + 8);
    } else {
      ctx.moveTo(x, y);
      ctx.lineTo(x - 5, y - 8);
      ctx.lineTo(x + 5, y - 8);
    }
    ctx.closePath();
    ctx.fill();
    ctx.shadowBlur = 0;
  }

  function createTape(canvas, options) {
    const ctx = canvas.getContext("2d");
    const candles = seedCandles(options.count, options.start);
    if (options.trades) seedTrades(candles);
    candles.forEach((c, i) => {
      c.slot = i;
    });
    const live = Object.assign({}, candles[candles.length - 1], { id: nextId++, slot: candles.length });
    live.target = live.close;
    const state = {
      candles: candles,
      camera: 0,
      cameraReady: false,
      nextSlot: candles.length + 1,
      last: performance.now(),
      live: live,
      tick: 0,
      idle: 2,
      position: null,
      viewMin: null,
      viewMax: null,
    };

    const resize = () => {
      const dpr = window.devicePixelRatio || 1;
      const w = canvas.clientWidth || canvas.width;
      const h = canvas.clientHeight || canvas.height;
      canvas.width = Math.floor(w * dpr);
      canvas.height = Math.floor(h * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      state.cssW = w;
      state.cssH = h;
    };

    const draw = (now) => {
      const dt = Math.min(40, now - state.last) / 1000;
      state.last = now;
      const w = state.cssW;
      const h = state.cssH;
      const pad = options.pad;
      const step = options.step;
      const body = options.body;

      if (!state.cameraReady && w) {
        state.camera = state.live.slot * step - (w - pad.r - body / 2);
        state.cameraReady = true;
      }

      if (!reduced && !document.hidden) {
        state.camera += options.speed * dt;
        state.tick += dt;
        const forming = state.live;
        if (forming.target == null) forming.target = forming.close;
        forming.target += (Math.random() - 0.48) * 0.03;
        forming.close += (forming.target - forming.close) * Math.min(1, dt * 2);
        forming.high = Math.max(forming.high, forming.close, forming.open);
        forming.low = Math.min(forming.low, forming.close, forming.open);
        if (state.tick > 1.8) {
          state.candles.push({
            id: forming.id,
            slot: forming.slot,
            open: forming.open,
            close: forming.close,
            high: forming.high,
            low: forming.low,
            vol: forming.vol,
            signal: null,
            signalText: "",
            linkedId: null,
          });
          if (options.trades) maybeTrade(state);
          state.live = makeCandle(forming.close);
          state.live.slot = state.nextSlot++;
          state.live.target = state.live.close;
          state.tick = 0;
          if (state.candles.length > options.count + 4) state.candles.shift();
        }
      }

      const series = state.candles.concat([state.live]);
      const lows = series.map((c) => c.low);
      const highs = series.map((c) => c.high);
      const min = Math.min.apply(null, lows);
      const max = Math.max.apply(null, highs);
      if (state.viewMin == null) {
        state.viewMin = min;
        state.viewMax = max;
      } else {
        const ease = Math.min(1, dt * 1.15);
        state.viewMin += (min - state.viewMin) * ease;
        state.viewMax += (max - state.viewMax) * ease;
      }
      const span = state.viewMax - state.viewMin || 1;
      const yAt = (v) => pad.t + ((state.viewMax - v) / span) * (h - pad.t - pad.b);
      const xAt = (slot) => slot * step - state.camera;

      ctx.clearRect(0, 0, w, h);

      if (options.grid) {
        ctx.strokeStyle = "rgba(232,237,245,0.05)";
        ctx.lineWidth = 1;
        for (let i = 0; i < 4; i++) {
          const y = pad.t + ((h - pad.t - pad.b) * i) / 3;
          ctx.beginPath();
          ctx.moveTo(0, y);
          ctx.lineTo(w, y);
          ctx.stroke();
        }
      }

      if (options.trades) {
        ctx.lineWidth = 1.2;
        ctx.setLineDash([4, 4]);
        series.forEach((c) => {
          if (c.signal !== "tp" || !c.linkedId) return;
          const entry = series.find((item) => item.id === c.linkedId);
          if (!entry) return;
          ctx.strokeStyle = "rgba(255,193,77,0.35)";
          ctx.beginPath();
          ctx.moveTo(xAt(entry.slot), yAt(entry.close));
          ctx.lineTo(xAt(c.slot), yAt(c.close));
          ctx.stroke();
        });
        if (state.position) {
          const entry = series.find((item) => item.id === state.position.entryId);
          if (entry) {
            ctx.strokeStyle = state.position.side === "buy" ? "rgba(61,255,164,0.25)" : "rgba(255,107,134,0.25)";
            ctx.beginPath();
            ctx.moveTo(xAt(entry.slot), yAt(state.position.entryPrice));
            ctx.lineTo(xAt(state.live.slot), yAt(state.live.close));
            ctx.stroke();
          }
        }
        ctx.setLineDash([]);
      }

      for (let i = series.length - 1; i >= 0; i--) {
        const x = xAt(series[i].slot);
        if (x < -30 || x > w + 30) continue;
        const c = series[i];
        const color = c.close >= c.open ? "#3dffa4" : "#ff6b86";
        const yOpen = yAt(c.open);
        const yClose = yAt(c.close);
        const top = Math.min(yOpen, yClose);
        const height = Math.max(2, Math.abs(yClose - yOpen));

        ctx.strokeStyle = color;
        ctx.globalAlpha = options.alpha;
        ctx.lineWidth = 1.2;
        ctx.beginPath();
        ctx.moveTo(x, yAt(c.high));
        ctx.lineTo(x, yAt(c.low));
        ctx.stroke();
        ctx.fillStyle = color;
        ctx.shadowColor = color;
        ctx.shadowBlur = 0;
        ctx.fillRect(x - body / 2, top, body, height);
        ctx.shadowBlur = 0;
        ctx.globalAlpha = 1;

        if (!options.trades || !c.signal) continue;
        if (c.signal === "buy") {
          drawArrow(ctx, x, yAt(c.low) + 3, "up", "#3dffa4");
          drawLabel(ctx, c.signalText || "Покупка", x, yAt(c.low) + 14, "#3dffa4");
        } else if (c.signal === "sell") {
          drawArrow(ctx, x, yAt(c.high) - 3, "down", "#ff6b86");
          drawLabel(ctx, c.signalText || "Продажа", x, yAt(c.high) - 28, "#ff6b86");
        } else if (c.signal === "tp") {
          drawLabel(ctx, c.signalText || "Профит", x, yAt(c.close) - 22, "#ffc14d");
        }
      }

      const last = series[series.length - 1];
      if (options.priceLine && last) {
        const y = yAt(last.close);
        ctx.setLineDash([4, 6]);
        ctx.strokeStyle = last.close >= last.open ? "rgba(61,255,164,0.45)" : "rgba(255,107,134,0.45)";
        ctx.beginPath();
        ctx.moveTo(0, y);
        ctx.lineTo(w, y);
        ctx.stroke();
        ctx.setLineDash([]);
      }
    };

    resize();
    window.addEventListener("resize", resize);
    return { draw };
  }

  function mountCandles() {
    const tapes = [];
    const bg = $("#candle-bg");
    if (bg) {
      tapes.push(
        createTape(bg, {
          count: 90,
          start: 48,
          speed: 8,
          step: 16,
          body: 8,
          alpha: 0.38,
          grid: false,
          priceLine: false,
          trades: true,
          pad: { t: 40, r: 16, b: 48 },
        })
      );
    }

    const loop = (now) => {
      tapes.forEach((item) => item.draw(now));
      requestAnimationFrame(loop);
    };
    requestAnimationFrame(loop);
  }

  function mountReveal() {
    const nodes = $$(".glass, .hero-copy, .hero-panel, .steps li, .section-head, .hero-stats > div").filter(
      (node) => !node.closest("#case")
    );
    if (reduced) {
      nodes.forEach((node) => node.classList.add("is-in"));
      return;
    }
    nodes.forEach((node) => node.classList.add("reveal"));
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-in");
            io.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12, rootMargin: "0px 0px -40px 0px" }
    );
    nodes.forEach((node) => io.observe(node));
  }

  function mountFx() {
    const canvas = $("#fx-layer");
    if (!canvas || reduced) return;
    const ctx = canvas.getContext("2d");
    const mouse = { x: window.innerWidth * 0.6, y: window.innerHeight * 0.35, tx: 0, ty: 0, on: false };
    const rings = [];
    const ticks = [];
    const particles = Array.from({ length: 48 }, () => ({
      x: Math.random() * window.innerWidth,
      y: Math.random() * window.innerHeight,
      v: 0.15 + Math.random() * 0.35,
      s: 0.6 + Math.random() * 1.6,
      hue: Math.random() > 0.7 ? "255,107,134" : Math.random() > 0.45 ? "61,255,164" : "122,162,255",
    }));

    const resize = () => {
      const dpr = window.devicePixelRatio || 1;
      canvas.width = Math.floor(window.innerWidth * dpr);
      canvas.height = Math.floor(window.innerHeight * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    };

    window.addEventListener("resize", resize);
    window.addEventListener("pointermove", (event) => {
      mouse.tx = event.clientX;
      mouse.ty = event.clientY;
      mouse.on = true;
    });
    window.addEventListener("pointerleave", () => {
      mouse.on = false;
    });
    window.addEventListener("pointerdown", (event) => {
      rings.push({ x: event.clientX, y: event.clientY, life: 0 });
    });

    const spawnTick = () => {
      if (document.hidden) return;
      const buy = Math.random() > 0.42;
      ticks.push({
        x: Math.random() * window.innerWidth,
        y: window.innerHeight * (0.15 + Math.random() * 0.55),
        life: 0,
        text: buy ? "BUY" : "SELL",
        color: buy ? "61,255,164" : "255,107,134",
      });
      if (ticks.length > 8) ticks.shift();
    };
    window.setInterval(spawnTick, 2200);
    spawnTick();

    const draw = () => {
      const w = window.innerWidth;
      const h = window.innerHeight;
      mouse.x += (mouse.tx - mouse.x) * 0.12;
      mouse.y += (mouse.ty - mouse.y) * 0.12;
      ctx.clearRect(0, 0, w, h);

      particles.forEach((p) => {
        p.y -= p.v;
        if (mouse.on) {
          p.x += (mouse.x - p.x) * 0.0009;
          p.y += (mouse.y - p.y) * 0.0006;
        }
        if (p.y < -10) {
          p.y = h + 10;
          p.x = Math.random() * w;
        }
        ctx.fillStyle = "rgba(" + p.hue + ",0.35)";
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.s, 0, Math.PI * 2);
        ctx.fill();
      });

      if (mouse.on) {
        const glow = ctx.createRadialGradient(mouse.x, mouse.y, 0, mouse.x, mouse.y, 180);
        glow.addColorStop(0, "rgba(61,255,164,0.16)");
        glow.addColorStop(1, "rgba(61,255,164,0)");
        ctx.fillStyle = glow;
        ctx.fillRect(0, 0, w, h);

        ctx.strokeStyle = "rgba(61,255,164,0.12)";
        ctx.lineWidth = 1;
        ctx.setLineDash([3, 8]);
        ctx.beginPath();
        ctx.moveTo(0, mouse.y);
        ctx.lineTo(w, mouse.y);
        ctx.moveTo(mouse.x, 0);
        ctx.lineTo(mouse.x, h);
        ctx.stroke();
        ctx.setLineDash([]);
        ctx.fillStyle = "rgba(61,255,164,0.7)";
        ctx.beginPath();
        ctx.arc(mouse.x, mouse.y, 2.2, 0, Math.PI * 2);
        ctx.fill();
      }

      ticks.forEach((t) => {
        t.life += 0.008;
        t.y -= 0.18;
        const alpha = Math.max(0, 0.45 - t.life);
        ctx.font = "600 11px IBM Plex Mono, monospace";
        ctx.fillStyle = "rgba(" + t.color + "," + alpha + ")";
        ctx.fillText(t.text, t.x, t.y);
      });

      rings.forEach((r) => {
        r.life += 0.02;
        ctx.strokeStyle = "rgba(61,255,164," + Math.max(0, 0.28 - r.life) + ")";
        ctx.lineWidth = 1.2;
        ctx.beginPath();
        ctx.arc(r.x, r.y, 12 + r.life * 70, 0, Math.PI * 2);
        ctx.stroke();
      });
      for (let i = rings.length - 1; i >= 0; i--) if (rings[i].life > 1) rings.splice(i, 1);

      requestAnimationFrame(draw);
    };

    resize();
    requestAnimationFrame(draw);

    const orbs = $$(".orb");
    window.addEventListener("pointermove", (event) => {
      const dx = (event.clientX / window.innerWidth - 0.5) * 24;
      const dy = (event.clientY / window.innerHeight - 0.5) * 18;
      orbs.forEach((orb, i) => {
        const k = 1 + i * 0.25;
        orb.style.translate = dx * k + "px " + dy * k + "px";
      });
    });
  }

  function mountLog() {
    const list = $("#robot-log-list");
    if (!list || reduced) return;
    const venues = ["FINAM", "BYBIT", "OKX", "BINANCE"];
    const pairs = ["CNY", "BTC", "ETH", "Si", "SBER"];
    const lines = [];

    const push = () => {
      const roll = Math.random();
      const kind = roll > 0.68 ? "tp" : roll > 0.34 ? "buy" : "sell";
      const label = kind === "tp" ? "PROFIT" : kind === "buy" ? "BUY" : "SELL";
      const venue = venues[Math.floor(Math.random() * venues.length)];
      const pair = pairs[Math.floor(Math.random() * pairs.length)];
      const pnl = (Math.random() * 1.8 + 0.2).toFixed(2);
      const text =
        kind === "tp"
          ? venue + " · " + pair + " · " + label + " +" + pnl + "%"
          : venue + " · " + pair + " · " + label;
      lines.unshift({ kind: kind, text: text });
      if (lines.length > 5) lines.pop();
      list.innerHTML = lines
        .map((item) => "<li class=\"" + item.kind + "\">" + item.text + "</li>")
        .join("");
    };

    push();
    window.setInterval(push, 2600);
  }

  function mountTilt() {
    if (reduced || window.matchMedia("(pointer: coarse)").matches) return;
    $$(".hero-panel, .market-card, .cards-4 .glass").forEach((card) => {
      card.addEventListener("pointermove", (event) => {
        const box = card.getBoundingClientRect();
        const x = (event.clientX - box.left) / box.width - 0.5;
        const y = (event.clientY - box.top) / box.height - 0.5;
        card.style.transform = "perspective(700px) rotateY(" + x * 7 + "deg) rotateX(" + -y * 7 + "deg) translateY(-4px)";
      });
      card.addEventListener("pointerleave", () => {
        card.style.transform = "";
      });
    });
  }

  function mountScrollProgress() {
    const bar = $("#scroll-progress");
    if (!bar) return;
    const sync = () => {
      const max = document.documentElement.scrollHeight - window.innerHeight;
      const p = max > 0 ? window.scrollY / max : 0;
      bar.style.transform = "scaleX(" + Math.min(1, Math.max(0, p)) + ")";
    };
    window.addEventListener("scroll", sync, { passive: true });
    window.addEventListener("resize", sync);
    sync();
  }

  mountCandles();
  mountReveal();
  mountFx();
  mountLog();
  mountTilt();
  mountScrollProgress();
})();
