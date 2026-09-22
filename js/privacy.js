(function () {
  var offer = document.getElementById("offer-modal");
  var privacy = document.getElementById("privacy-modal");
  if (!offer && !privacy) return;

  var pathOf = function (page) {
    var path = (location.pathname || "/").replace(/\/+$/, "") + "/";
    return path === "/" + page + "/";
  };

  var closeAll = function (clearHash) {
    if (offer) offer.hidden = true;
    if (privacy) privacy.hidden = true;
    document.body.classList.remove("modal-open");
    if (clearHash && (location.hash === "#offer" || location.hash === "#privacy")) {
      history.replaceState(null, "", location.pathname + location.search);
    }
  };

  var open = function (kind) {
    closeAll(false);
    var node = kind === "offer" ? offer : privacy;
    if (!node) return;
    node.hidden = false;
    document.body.classList.add("modal-open");
    var card = node.querySelector(".modal-card");
    if (card) card.scrollTop = 0;
  };

  document.addEventListener("click", function (event) {
    if (event.target.closest("[data-legal-close]")) {
      event.preventDefault();
      closeAll(true);
      return;
    }
    var offerBtn = event.target.closest("[data-offer]");
    if (offerBtn) {
      if (pathOf("offer")) return;
      event.preventDefault();
      open("offer");
      return;
    }
    var privacyBtn = event.target.closest("[data-privacy]");
    if (privacyBtn) {
      if (pathOf("privacy")) return;
      event.preventDefault();
      open("privacy");
    }
  });

  document.addEventListener("keydown", function (event) {
    if (event.key !== "Escape") return;
    if ((offer && !offer.hidden) || (privacy && !privacy.hidden)) {
      closeAll(true);
    }
  });

  if (location.hash === "#offer" && !pathOf("offer")) open("offer");
  if (location.hash === "#privacy" && !pathOf("privacy")) open("privacy");
  window.addEventListener("hashchange", function () {
    if (location.hash === "#offer") open("offer");
    else if (location.hash === "#privacy") open("privacy");
    else closeAll(false);
  });
})();
