/* ============================================================
   GameIndo theme — front-end interactivity.
   Content is server-rendered by PHP; this only wires up the
   chrome behaviours the original js/ files handled: drawer,
   mega-menu, newsletter, load-more, author tabs, search filter,
   and the article share/copy actions.
   ============================================================ */
(function () {
  "use strict";

  /* ---- Mobile drawer ---------------------------------------- */
  (function () {
    var drawer = document.getElementById("gi-drawer");
    if (!drawer) return;
    var openers = document.querySelectorAll("[data-drawer-open]");
    var closers = drawer.querySelectorAll("[data-drawer-close]");
    function open() {
      drawer.classList.add("is-open");
      openers.forEach(function (b) { b.setAttribute("aria-expanded", "true"); });
      document.body.style.overflow = "hidden";
    }
    function close() {
      drawer.classList.remove("is-open");
      openers.forEach(function (b) { b.setAttribute("aria-expanded", "false"); });
      document.body.style.overflow = "";
    }
    openers.forEach(function (b) { b.addEventListener("click", open); });
    closers.forEach(function (el) { el.addEventListener("click", close); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") close(); });
  })();

  /* ---- Hero slider (auto-advancing latest-articles carousel) */
  (function () {
    var slider = document.getElementById("gi-hero-slider");
    var track = document.getElementById("gi-hero-slider-track");
    var dotsWrap = document.getElementById("gi-hero-slider-dots");
    if (!slider || !track) return;
    var slides = Array.prototype.slice.call(track.children);
    if (slides.length < 2) return;

    var index = 0;
    var autoplayMs = parseInt(slider.getAttribute("data-autoplay"), 10) || 6000;
    var timer = null;
    var dots = [];

    if (dotsWrap) {
      slides.forEach(function (_, i) {
        var dot = document.createElement("button");
        dot.type = "button";
        dot.className = "gi-hero-slider__dot";
        dot.setAttribute("aria-label", "Ke slide " + (i + 1));
        dot.addEventListener("click", function () { goTo(i); restart(); });
        dotsWrap.appendChild(dot);
      });
      dots = Array.prototype.slice.call(dotsWrap.children);
    }

    function updateDots() {
      dots.forEach(function (d, i) { d.setAttribute("aria-current", i === index ? "true" : "false"); });
    }
    function goTo(i) {
      index = (i + slides.length) % slides.length;
      track.scrollTo({ left: slides[index].offsetLeft, behavior: "smooth" });
      updateDots();
    }
    function next() { goTo(index + 1); }
    function prev() { goTo(index - 1); }
    function play() { stop(); timer = setInterval(next, autoplayMs); }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }
    function restart() { play(); }

    var prevBtn = slider.querySelector("[data-slider-prev]");
    var nextBtn = slider.querySelector("[data-slider-next]");
    if (prevBtn) prevBtn.addEventListener("click", function () { prev(); restart(); });
    if (nextBtn) nextBtn.addEventListener("click", function () { next(); restart(); });

    slider.addEventListener("mouseenter", stop);
    slider.addEventListener("mouseleave", play);
    slider.addEventListener("touchstart", stop, { passive: true });
    slider.addEventListener("touchend", play);

    slider.setAttribute("tabindex", "0");
    slider.addEventListener("keydown", function (e) {
      if (e.key === "ArrowRight") { next(); restart(); }
      else if (e.key === "ArrowLeft") { prev(); restart(); }
    });

    // Keep the active dot in sync when the user drags/swipes the track directly.
    var scrollTimer = null;
    track.addEventListener("scroll", function () {
      clearTimeout(scrollTimer);
      scrollTimer = setTimeout(function () {
        var nearest = 0;
        var best = Infinity;
        slides.forEach(function (s, i) {
          var d = Math.abs(s.offsetLeft - track.scrollLeft);
          if (d < best) { best = d; nearest = i; }
        });
        index = nearest;
        updateDots();
      }, 100);
    });

    updateDots();
    play();
  })();

  /* ---- Mega menu -------------------------------------------- */
  (function () {
    var toggle = document.getElementById("gi-megamenu-toggle");
    var panel = document.getElementById("gi-megamenu");
    if (!toggle || !panel) return;
    toggle.addEventListener("click", function () {
      var open = panel.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
  })();

  /* ---- Newsletter opt-in (AJAX) ----------------------------- */
  (function () {
    var form = document.getElementById("gi-newsletter-form");
    var note = document.getElementById("gi-newsletter-note");
    if (!form) return;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var data = window.GameIndoData || {};
      var email = (form.querySelector("input[name=email]") || {}).value || "";
      if (!data.ajaxUrl) {
        if (note) note.textContent = "Terima kasih! Cek inbox kamu untuk konfirmasi.";
        form.reset();
        return;
      }
      var body = new URLSearchParams();
      body.set("action", "gameindo_newsletter");
      body.set("nonce", data.nonce || "");
      body.set("email", email);
      fetch(data.ajaxUrl, { method: "POST", body: body, credentials: "same-origin" })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (note) note.textContent = (res && res.data && res.data.message) || "Terima kasih!";
          if (res && res.success) form.reset();
        })
        .catch(function () { if (note) note.textContent = "Gagal mendaftar, coba lagi."; });
    });
  })();

  /* ---- Load more (esports grid + search results) ------------ */
  (function () {
    var btn = document.querySelector("[data-load-more]");
    if (!btn) return;
    var container = document.getElementById("gi-esports-grid") || document.getElementById("gi-search-results");
    if (!container) return;
    var step = parseInt(btn.getAttribute("data-step"), 10) || 3;

    btn.addEventListener("click", function () {
      var hidden = Array.prototype.slice.call(container.querySelectorAll(".gi-is-hidden"));
      var revealedCards = 0;
      for (var i = 0; i < hidden.length && revealedCards < step; i++) {
        hidden[i].classList.remove("gi-is-hidden");
        if (!hidden[i].classList.contains("gi-result-divider")) revealedCards++;
      }
      if (!container.querySelector(".gi-is-hidden")) btn.style.display = "none";
    });
  })();

  /* ---- Author archive tabs ---------------------------------- */
  (function () {
    var tabsNav = document.getElementById("gi-author-tabs");
    var grid = document.getElementById("gi-author-grid");
    if (!tabsNav || !grid) return;
    var tabs = tabsNav.querySelectorAll(".gi-tab");
    var initial = Array.prototype.slice.call(grid.children);

    function apply(sort) {
      var nodes = initial.slice();
      if (sort === "popular") {
        nodes.sort(function (a, b) {
          return (parseInt(b.getAttribute("data-reads"), 10) || 0) - (parseInt(a.getAttribute("data-reads"), 10) || 0);
        });
      }
      nodes.forEach(function (n) {
        var show = true;
        if (sort === "series") show = (n.getAttribute("data-sub") === "MPL ID");
        n.style.display = show ? "" : "none";
        grid.appendChild(n);
      });
    }

    tabs.forEach(function (tab) {
      tab.addEventListener("click", function () {
        tabs.forEach(function (t) { t.removeAttribute("aria-current"); });
        tab.setAttribute("aria-current", "true");
        apply(tab.getAttribute("data-sort") || "latest");
      });
    });
  })();

  /* ---- Search pillar filter --------------------------------- */
  (function () {
    var filters = document.getElementById("gi-search-filters");
    var results = document.getElementById("gi-search-results");
    if (!filters || !results) return;
    var pills = filters.querySelectorAll(".gi-filter");
    var countEl = document.getElementById("gi-search-count");
    var moreBtn = document.getElementById("gi-search-more");

    pills.forEach(function (pill) {
      pill.addEventListener("click", function () {
        pills.forEach(function (p) { p.removeAttribute("aria-current"); });
        pill.setAttribute("aria-current", "true");
        var pillar = pill.getAttribute("data-pillar") || "";
        var cards = results.querySelectorAll(".gi-card--h");
        var dividers = results.querySelectorAll(".gi-result-divider");
        var shown = 0;
        cards.forEach(function (c) {
          var match = !pillar || c.getAttribute("data-pillar") === pillar;
          c.classList.remove("gi-is-hidden");
          c.style.display = match ? "" : "none";
          if (match) shown++;
        });
        // With an active filter, drop the dividers and the load-more button.
        dividers.forEach(function (d) { d.style.display = pillar ? "none" : ""; d.classList.remove("gi-is-hidden"); });
        if (moreBtn) moreBtn.style.display = pillar ? "none" : "";
        if (countEl) countEl.textContent = shown + " hasil";
      });
    });
  })();

  /* ---- Article share / copy link ---------------------------- */
  (function () {
    var shareBtn = document.querySelector("[data-share]");
    if (shareBtn) {
      shareBtn.addEventListener("click", function () {
        var payload = { title: document.title, url: window.location.href };
        if (navigator.share) { navigator.share(payload).catch(function () {}); }
        else if (navigator.clipboard) { navigator.clipboard.writeText(window.location.href); }
      });
    }
    var copyBtn = document.querySelector("[data-copy-link]");
    if (copyBtn && navigator.clipboard) {
      copyBtn.addEventListener("click", function () {
        navigator.clipboard.writeText(window.location.href).then(function () {
          var prev = copyBtn.getAttribute("aria-label");
          copyBtn.setAttribute("aria-label", "Tautan disalin!");
          setTimeout(function () { copyBtn.setAttribute("aria-label", prev); }, 1500);
        });
      });
    }
  })();

})();
