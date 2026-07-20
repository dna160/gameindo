/* GameIndo — home page controller: fetches CMS data, renders into mount points. */
(function () {
  "use strict";
  var T = window.GITemplates;
  var PILLAR_HREFS = { home: "index.html", esports: "esports.html" };
  var BAND_ORDER = ["esports", "home", "streamer", "tech", "entertainment"];

  function renderTicker(items) {
    var track = document.getElementById("gi-ticker-track");
    if (!track) return;
    var html = items.map(T.tickerItem).join("");
    track.innerHTML = html + html; // duplicated for the seamless 50% marquee loop
  }

  function renderHotTopics(topics) {
    var row = document.getElementById("gi-hottopics-row");
    if (!row) return;
    var label = row.querySelector(".gi-hottopics__label");
    row.innerHTML = "";
    if (label) row.appendChild(label);
    topics.forEach(function (t) {
      var a = document.createElement("a");
      a.className = "gi-hottopics__item";
      a.href = "search.html?q=" + encodeURIComponent(t.query || t.label);
      a.textContent = t.label;
      row.appendChild(a);
    });
  }

  function wireMegaMenu() {
    var toggle = document.getElementById("gi-megamenu-toggle");
    var panel = document.getElementById("gi-megamenu");
    if (!toggle || !panel) return;
    toggle.addEventListener("click", function () {
      var open = panel.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
  }

  function renderMegaMenuTrending(posts) {
    var mount = document.getElementById("gi-megamenu-trending");
    if (!mount) return;
    mount.innerHTML = posts.map(function (p) {
      return '<a href="' + T.articleHref(p) + '">' + T.esc(p.title) + "</a>";
    }).join("");
  }

  function renderHero(featured, trendingPosts) {
    var featureMount = document.getElementById("gi-hero-feature");
    if (featureMount && featured) featureMount.innerHTML = T.feature(featured);

    var trendingMount = document.getElementById("gi-hero-trending");
    if (trendingMount) {
      trendingMount.innerHTML = trendingPosts.map(function (p) { return T.card(p, { variant: "h" }); }).join("");
    }
  }

  function renderMatchPanel(data) {
    var rows = document.getElementById("gi-matchpanel-rows");
    if (rows) rows.innerHTML = data.matches.map(T.matchPanelRow).join("");
    var meta = document.getElementById("gi-matchpanel-meta");
    if (meta) meta.textContent = data.competition;
  }

  function renderMobileMatches(data) {
    var mount = document.getElementById("gi-mobile-matches-row");
    if (!mount) return;
    var featured = data.matches.filter(function (m) { return m.status !== "scheduled"; }).slice(0, 2);
    if (!featured.length) featured = data.matches.slice(0, 2);
    mount.innerHTML = featured.map(function (m) { return T.mobileMatchCard(m, data.competition); }).join("");
  }

  function renderLatest(posts) {
    var mount = document.getElementById("gi-latest-grid");
    if (!mount) return;
    mount.innerHTML = posts.map(function (p) { return T.card(p, { variant: "md" }); }).join("");
  }

  function renderTerpopuler(posts) {
    var mount = document.getElementById("gi-terpopuler-rail");
    if (!mount) return;
    var top = posts.filter(function (p) { return p.reads; })
      .sort(function (a, b) { return parseInt(b.reads) - parseInt(a.reads); })
      .slice(0, 5);
    mount.innerHTML = top.map(function (p, i) { return T.rankRow(p, i + 1, { thumb: true }); }).join("");
  }

  function wireNewsletter() {
    var form = document.getElementById("gi-newsletter-form");
    var note = document.getElementById("gi-newsletter-note");
    if (!form) return;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (note) note.textContent = "Terima kasih! Cek inbox kamu untuk konfirmasi.";
      form.reset();
    });
  }

  function renderPillarBands(allPosts, pillars) {
    var mount = document.getElementById("gi-pillar-bands");
    if (!mount) return;
    var pillarBySlug = {};
    pillars.forEach(function (p) { pillarBySlug[p.slug] = p; });

    mount.innerHTML = BAND_ORDER.map(function (slug, i) {
      var pillar = pillarBySlug[slug];
      var name = pillar ? pillar.name : T.pillarName(slug);
      var posts = allPosts.filter(function (p) { return p.pillar === slug; }).slice(0, 4);
      if (!posts.length) return "";
      var href = PILLAR_HREFS[slug] || "index.html#pillars";
      var cards = posts.map(function (p) {
        return T.card(p, { variant: "sm", pillLabel: p.subcategory || name });
      }).join("");
      return (
        '<section class="gi-pillarband' + (i % 2 === 1 ? " gi-pillarband--alt" : "") + '" data-pillar="' + slug + '" id="pillar-' + slug + '">' +
          '<div class="gi-pillarband__inner">' +
            '<div class="gi-section-head">' +
              '<div class="gi-section-head__main"><span class="gi-section-head__tick" aria-hidden="true"></span>' +
                '<div><span class="gi-section-head__eyebrow">Pillar</span><h2 class="gi-section-head__title">' + T.esc(name) + "</h2></div></div>" +
              '<a class="gi-section-head__link" href="' + href + '">View More <span aria-hidden="true">→</span></a>' +
            "</div>" +
            '<div class="gi-grid-4" style="margin-top:20px">' + cards + "</div>" +
          "</div>" +
        "</section>"
      );
    }).join("");
  }

  function renderPillarTiles(pillars) {
    var mount = document.getElementById("gi-pillar-tiles");
    if (!mount) return;
    mount.innerHTML = pillars.map(function (p) {
      return T.pillarTile(p, PILLAR_HREFS[p.slug] || "index.html#pillars");
    }).join("");
  }

  Promise.all([
    CMS.getTicker(),
    CMS.getTopics(),
    CMS.getPosts({}),
    CMS.getMatches(),
    CMS.getPillars()
  ]).then(function (results) {
    var ticker = results[0];
    var topics = results[1];
    var allPosts = results[2].items;
    var matches = results[3];
    var pillars = results[4];

    var featured = allPosts.find(function (p) { return p.featured; }) || allPosts[0];
    var spotlight = allPosts.filter(function (p) { return p.spotlight; });
    var heroTrending = spotlight.filter(function (p) { return p.pillar !== (featured ? featured.pillar : null); }).slice(0, 2);
    if (heroTrending.length < 2) {
      heroTrending = allPosts.filter(function (p) { return !featured || p.slug !== featured.slug; }).slice(0, 2);
    }
    var latest = allPosts.filter(function (p) {
      return (!featured || p.slug !== featured.slug) && heroTrending.indexOf(p) === -1;
    }).slice(0, 4);
    var trendingSekarang = allPosts.filter(function (p) { return p.reads; })
      .sort(function (a, b) { return parseInt(b.reads) - parseInt(a.reads); })
      .slice(0, 3);

    renderTicker(ticker);
    renderHotTopics(topics);
    wireMegaMenu();
    renderMegaMenuTrending(trendingSekarang);
    renderHero(featured, heroTrending);
    renderMatchPanel(matches);
    renderMobileMatches(matches);
    renderLatest(latest);
    renderTerpopuler(allPosts);
    wireNewsletter();
    renderPillarBands(allPosts, pillars);
    renderPillarTiles(pillars);
  }).catch(function (err) {
    console.error("GameIndo: failed to load home page content", err);
  });
})();
