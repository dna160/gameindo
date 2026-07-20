/* GameIndo — esports pillar page controller. */
(function () {
  "use strict";
  var T = window.GITemplates;
  var PAGE_SIZE = 3;
  var shown = PAGE_SIZE;
  var allEsportsPosts = [];

  function renderTicker(items) {
    var track = document.getElementById("gi-ticker-track");
    if (!track) return;
    var html = items.map(T.tickerItem).join("");
    track.innerHTML = html + html;
  }

  function renderFeature(post) {
    var mount = document.getElementById("gi-esports-feature");
    if (mount && post) mount.innerHTML = T.feature(post, { pillLabel: post.subcategory || "Esports" });
  }

  function renderStandings(data) {
    var head = document.getElementById("gi-standings-title");
    var meta = document.getElementById("gi-standings-meta");
    var rows = document.getElementById("gi-standings-rows");
    if (head) head.textContent = data.competition;
    if (meta) meta.textContent = data.season_label;
    if (rows) rows.innerHTML = data.rows.map(T.standingsRow).join("");
  }

  function renderGrid() {
    var mount = document.getElementById("gi-esports-grid");
    if (!mount) return;
    var visible = allEsportsPosts.slice(0, shown);
    mount.innerHTML = visible.map(function (p) {
      return T.card(p, { variant: "md", pillLabel: p.subcategory || "Esports" });
    }).join("");
    var moreBtn = document.getElementById("gi-esports-more");
    if (moreBtn) moreBtn.style.display = shown >= allEsportsPosts.length ? "none" : "";
  }

  Promise.all([
    CMS.getTicker(),
    CMS.getPosts({ pillar: "esports" }),
    CMS.getStandings()
  ]).then(function (results) {
    var ticker = results[0];
    var posts = results[1].items;
    var standings = results[2];

    var feature = posts[0];
    allEsportsPosts = posts.filter(function (p) { return p.slug !== feature.slug; });
    renderTicker(ticker);
    renderFeature(feature);
    renderStandings(standings);
    renderGrid();

    var moreBtn = document.getElementById("gi-esports-more");
    if (moreBtn) {
      moreBtn.addEventListener("click", function () {
        shown += PAGE_SIZE;
        renderGrid();
      });
    }
  }).catch(function (err) {
    console.error("GameIndo: failed to load esports page content", err);
  });
})();
