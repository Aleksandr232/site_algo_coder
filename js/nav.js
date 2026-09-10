(function () {
  var burger = document.getElementById("burger");
  var nav = document.getElementById("nav");
  var backdrop = document.getElementById("nav-backdrop");
  var more = nav && nav.querySelector(".nav-more");
  var moreBtn = more && more.querySelector(".nav-more-btn");

  function isMobile() {
    return window.matchMedia("(max-width: 960px)").matches;
  }

  function closeMore() {
    if (!more || !moreBtn) return;
    more.classList.remove("is-open");
    moreBtn.setAttribute("aria-expanded", "false");
  }

  function setMenuOpen(open) {
    if (!nav || !burger) return;
    nav.classList.toggle("is-open", open);
    burger.classList.toggle("is-open", open);
    burger.setAttribute("aria-expanded", open ? "true" : "false");
    burger.setAttribute("aria-label", open ? "Закрыть меню" : "Открыть меню");
    document.body.classList.toggle("nav-open", open);
    if (backdrop) backdrop.classList.toggle("is-open", open);
    if (!open) {
      closeMore();
      showMobileCta();
    }
  }

  if (burger && nav) {
    burger.addEventListener("click", function (event) {
      event.stopPropagation();
      setMenuOpen(!nav.classList.contains("is-open"));
    });
    nav.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function (event) {
        if (!isMobile()) {
          closeMore();
          return;
        }
        event.stopPropagation();
        var href = link.href;
        if (!href) {
          setMenuOpen(false);
          return;
        }
        event.preventDefault();
        setMenuOpen(false);
        window.setTimeout(function () {
          window.location.assign(href);
        }, 0);
      });
    });
  }

  if (backdrop) {
    backdrop.addEventListener("click", function () {
      setMenuOpen(false);
    });
  }

  if (more && moreBtn) {
    moreBtn.addEventListener("click", function (event) {
      event.stopPropagation();
      var open = !more.classList.contains("is-open");
      more.classList.toggle("is-open", open);
      moreBtn.setAttribute("aria-expanded", open ? "true" : "false");
    });
    document.addEventListener("click", function (event) {
      if (!more.contains(event.target) && (!burger || !burger.contains(event.target))) {
        closeMore();
      }
    });
  }

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") setMenuOpen(false);
  });

  window.addEventListener("resize", function () {
    if (!isMobile()) setMenuOpen(false);
  });

  var mobileCta = document.getElementById("mobile-cta");
  var ctaHideAt = document.getElementById("contact")
    || document.getElementById("order")
    || document.querySelector(".js-ready-form");
  var ctaTimer;

  function ctaTargetInView() {
    if (!ctaHideAt) return false;
    var rect = ctaHideAt.getBoundingClientRect();
    return rect.top < window.innerHeight - 80;
  }

  function showMobileCta() {
    if (!mobileCta) return;
    var show = isMobile() && !ctaTargetInView() && !document.body.classList.contains("nav-open");
    mobileCta.classList.toggle("is-visible", show);
  }

  function onScrollCta() {
    if (!mobileCta || !isMobile()) return;
    mobileCta.classList.remove("is-visible");
    clearTimeout(ctaTimer);
    ctaTimer = setTimeout(showMobileCta, 220);
  }

  if (mobileCta) {
    window.addEventListener("scroll", onScrollCta, { passive: true });
    window.addEventListener("resize", function () {
      if (!isMobile()) mobileCta.classList.remove("is-visible");
    });
  }
})();
