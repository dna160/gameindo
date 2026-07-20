/* GameIndo — search results page controller. Reads ?q= from the URL and
   filters client-side against the CMS post list (swap for a real WP
   ?search= REST query once API_BASE is set — see js/cms-client.js). */
(function () {
  "use strict";
  var T = window.GITemplates;
  var PAGE_SIZE = 5;
  var shown = PAGE_SIZE;
  var activePillar = null;
  var query = "";
  var allPosts = [];

  function qs(name) {
    return new URLSearchParams(window.location.search).get(name);
  }

  function matches(post) {
    if (activePillar && post.pillar !== activePillar) return false;
    if (!query) return true;
    var q = query.toLowerCase();
    return post.title.toLowerCase().indexOf(q) > -1 || post.excerpt.toLowerCase().indexOf(q) > -1;
  }

  function renderResults() {
    var results = allPosts.filter(matches);
    var mount = document.getElementById("gi-search-results");
    var countEl = document.getElementById("gi-search-count");
    var moreBtn = document.getElementById("gi-search-more");

    if (countEl) countEl.textContent = results.length + " hasil";

    if (!mount) return;
    if (!results.length) {
      mount.innerHTML = '<p style="color:var(--ink-4);font-size:14px;padding:24px 0">Tidak ada hasil untuk pencarian ini.</p>';
      if (moreBtn) moreBtn.style.display = "none";
      return;
    }

    var visible = results.slice(0, shown);
    mount.innerHTML = visible.map(function (p, i) {
      return T.card(p, { variant: "h" }) + (i < visible.length - 1 ? '<div class="gi-result-divider"></div>' : "");
    }).join("");

    if (moreBtn) moreBtn.style.display = shown >= results.length ? "none" : "";
  }

  function renderPopularRail() {
    var mount = document.getElementById("gi-search-popular");
    if (!mount) return;
    var top = allPosts.filter(function (p) { return p.reads; })
      .sort(function (a, b) { return parseInt(b.reads) - parseInt(a.reads); })
      .slice(0, 3);
    mount.innerHTML = top.map(function (p, i) { return T.rankRow(p, i + 1); }).join("");
  }

  function renderTagCloud() {
    var mount = document.getElementById("gi-search-tags");
    if (!mount) return;
    var seen = {}; var tags = [];
    allPosts.forEach(function (p) { (p.tags || []).forEach(function (t) {
      if (!seen[t]) { seen[t] = true; tags.push(t); }
    }); });
    mount.innerHTML = tags.slice(0, 6).map(function (t) {
      return T.tag(t, "search.html?q=" + encodeURIComponent(t));
    }).join("");
  }

  function wireFilters() {
    var pills = document.querySelectorAll("#gi-search-filters .gi-filter");
    pills.forEach(function (pill) {
      pill.addEventListener("click", function () {
        pills.forEach(function (p) { p.removeAttribute("aria-current"); });
        pill.setAttribute("aria-current", "true");
        activePillar = pill.getAttribute("data-pillar") || null;
        shown = PAGE_SIZE;
        renderResults();
      });
    });
  }

  function wireForm() {
    var form = document.querySelector(".gi-searchbar");
    var input = form ? form.querySelector("input[name=q]") : null;
    if (!form || !input) return;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      query = input.value.trim();
      shown = PAGE_SIZE;
      renderResults();
      document.title = (query ? "Hasil pencarian: " + query : "Cari") + " — GameIndo";
      var url = new URL(window.location.href);
      if (query) url.searchParams.set("q", query); else url.searchParams.delete("q");
      window.history.replaceState({}, "", url);
    });
  }

  query = qs("q") || "";
  var input = document.querySelector('.gi-searchbar input[name="q"]');
  if (input) input.value = query;
  if (query) document.title = "Hasil pencarian: " + query + " — GameIndo";

  CMS.getPosts({}).then(function (res) {
    allPosts = res.items;
    renderResults();
    renderPopularRail();
    renderTagCloud();
    wireFilters();
    wireForm();

    var moreBtn = document.getElementById("gi-search-more");
    if (moreBtn) moreBtn.addEventListener("click", function () { shown += PAGE_SIZE; renderResults(); });
  }).catch(function (err) {
    console.error("GameIndo: failed to load search results", err);
  });
})();
