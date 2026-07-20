/* ============================================================
   GAMEINDO — render templates.
   Pure functions turning CMS view-models (see js/cms-client.js)
   into the same markup/classes the original hand-authored pages
   used, so swapping static HTML for live data changes nothing
   visually. Every page controller in js/pages/*.js composes these.
   ============================================================ */
(function (global) {
  "use strict";

  // Fallback labels only — the source of truth is data/pillars.json
  // (or the WP categories endpoint once live).
  var PILLAR_NAMES = {
    home: "Video Game", esports: "Esports", streamer: "Streamer",
    tech: "Tech", entertainment: "Entertainment"
  };
  var MONTHS_ID = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];

  function esc(str) {
    return String(str == null ? "" : str).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
  function fmtDate(iso) {
    var d = new Date(iso);
    if (isNaN(d)) return "";
    return d.getDate() + " " + MONTHS_ID[d.getMonth()] + " " + d.getFullYear();
  }
  function articleHref(post) { return "article.html?slug=" + encodeURIComponent(post.slug); }
  function pillarName(pillar) { return PILLAR_NAMES[pillar] || pillar; }

  // variant: 'md' (default, esports/author grids) | 'sm' (dense homepage/related grids) | 'h' (search results)
  function card(post, opts) {
    opts = opts || {};
    var variant = opts.variant || "md";
    var pillLabel = opts.pillLabel || pillarName(post.pillar);
    var cls = "gi-card" + (variant === "sm" ? " gi-card--sm" : variant === "h" ? " gi-card--h" : "");
    var pillCls = variant === "h" ? "gi-pill gi-card__pill" : "gi-pill gi-pill--sm gi-card__pill";
    var showExcerpt = variant !== "sm";
    var showAuthor = opts.showAuthor !== false && variant !== "sm";
    var meta = (showAuthor ? '<span class="gi-card__author">' + esc(post.author.name) + '</span><span aria-hidden="true">·</span>' : "") +
      "<span>" + fmtDate(post.date) + "</span>";
    return (
      '<a class="' + cls + '" data-pillar="' + post.pillar + '" href="' + articleHref(post) + '">' +
        '<div class="gi-card__media"><img src="' + post.image + '" alt="' + esc(post.imageAlt) + '" loading="lazy">' +
          '<span class="' + pillCls + '">' + esc(pillLabel) + "</span></div>" +
        '<div class="gi-card__body"><h3 class="gi-card__title">' + esc(post.title) + "</h3>" +
          (showExcerpt ? '<p class="gi-card__excerpt">' + esc(post.excerpt) + "</p>" : "") +
          '<div class="gi-card__meta">' + meta + "</div></div>" +
      "</a>"
    );
  }

  function feature(post, opts) {
    opts = opts || {};
    var pillLabel = opts.pillLabel || pillarName(post.pillar);
    return (
      '<a class="gi-feature' + (opts.sm ? " gi-feature--sm" : "") + '" data-pillar="' + post.pillar + '" href="' + articleHref(post) + '">' +
        '<img src="' + post.image + '" alt="' + esc(post.imageAlt) + '">' +
        '<span class="gi-feature__bar" aria-hidden="true"></span>' +
        '<div class="gi-feature__content">' +
          '<span class="gi-pill">' + esc(pillLabel) + "</span>" +
          '<h2 class="gi-feature__title">' + esc(post.title) + "</h2>" +
          '<p class="gi-feature__excerpt">' + esc(post.excerpt) + "</p>" +
          '<div class="gi-feature__meta"><b>' + esc(post.author.name) + '</b><span aria-hidden="true">·</span><span>' + fmtDate(post.date) + "</span></div>" +
        "</div>" +
      "</a>"
    );
  }

  function spotlightItem(post) {
    return (
      '<a class="gi-spotlight__item" data-pillar="' + post.pillar + '" href="' + articleHref(post) + '">' +
        '<span class="gi-spotlight__cat">' + esc(pillarName(post.pillar)) + "</span>" +
        '<span class="gi-spotlight__title">' + esc(post.title) + "</span>" +
      "</a>"
    );
  }

  function rankRow(post, index, opts) {
    opts = opts || {};
    var num = String(index).length < 2 ? "0" + index : String(index);
    var thumb = opts.thumb
      ? '<span class="gi-rank__thumb"><img src="' + post.image + '" alt="" loading="lazy"></span>'
      : "";
    return (
      '<a class="gi-rank' + (opts.thumb ? "" : " gi-rank--no-thumb") + '" data-pillar="' + post.pillar + '" href="' + articleHref(post) + '">' +
        '<span class="gi-rank__num">' + num + "</span>" +
        thumb +
        "<span><span class=\"gi-rank__category\">" + esc(post.subcategory || pillarName(post.pillar)) + "</span>" +
        '<span class="gi-rank__title">' + esc(post.title) + "</span>" +
        '<span class="gi-rank__meta">' + esc(post.reads || "—") + " dibaca</span></span>" +
      "</a>"
    );
  }

  function tickerItem(item) {
    return '<a class="gi-ticker__item" href="' + esc(item.url || "#") + '">' + esc(item.text) + "</a>";
  }

  function pillarTile(pillar, href) {
    return (
      '<a data-pillar="' + pillar.slug + '" href="' + esc(href) + '">' +
        '<span class="gi-pillar-tiles__name">' + esc(pillar.name) + "</span>" +
        '<span class="gi-pillar-tiles__count">' + pillar.count.toLocaleString("id-ID") + " artikel →</span>" +
      "</a>"
    );
  }

  function standingsRow(row) {
    return (
      '<div class="gi-standings__row' + (row.top ? " gi-standings__row--top" : "") + '">' +
        '<span class="gi-standings__rank">' + row.rank + "</span>" +
        '<span class="gi-standings__team">' + esc(row.team) + "</span>" +
        '<span class="gi-standings__wl">' + esc(row.wl) + "</span>" +
        '<span class="gi-standings__pts">' + row.pts + "</span>" +
      "</div>"
    );
  }

  function matchPanelRow(m) {
    var score = m.score_a == null || m.score_b == null ? "vs" : m.score_a + " — " + m.score_b;
    return (
      '<div class="gi-matchpanel__row">' +
        '<span class="gi-matchpanel__team">' + esc(m.team_a) + "</span>" +
        '<span class="gi-matchpanel__score">' + esc(score) + "</span>" +
        '<span class="gi-matchpanel__team gi-matchpanel__team--right">' + esc(m.team_b) + "</span>" +
        '<span class="gi-matchpanel__status' + (m.status === "live" ? " gi-matchpanel__status--live" : "") + '">' + esc(m.status_label) + "</span>" +
      "</div>"
    );
  }

  function mobileMatchCard(m, competition) {
    var teams = m.team_a + " " + (m.score_a == null ? "vs" : m.score_a + " — " + m.score_b) + " " + m.team_b;
    return (
      '<div class="gi-mobile-matchcard' + (m.status === "live" ? " gi-mobile-matchcard--live" : "") + '">' +
        '<span class="gi-mobile-matchcard__status' + (m.status === "live" ? " gi-mobile-matchcard__status--live" : "") + '">' + esc(m.status_label) + "</span>" +
        '<span class="gi-mobile-matchcard__teams">' + esc(teams) + "</span>" +
        '<span class="gi-mobile-matchcard__comp">' + esc(competition) + "</span>" +
      "</div>"
    );
  }

  function tag(text, href) {
    return '<a class="gi-tag" href="' + esc(href) + '">#' + esc(String(text).toUpperCase()) + "</a>";
  }

  global.GITemplates = {
    esc: esc, fmtDate: fmtDate, pillarName: pillarName, articleHref: articleHref,
    card: card, feature: feature, spotlightItem: spotlightItem, rankRow: rankRow,
    tickerItem: tickerItem, pillarTile: pillarTile, standingsRow: standingsRow,
    matchPanelRow: matchPanelRow, mobileMatchCard: mobileMatchCard, tag: tag
  };
})(window);
