/* GameIndo — author profile page controller. Reads ?slug= from the URL. */
(function () {
  "use strict";
  var T = window.GITemplates;
  var authorPosts = [];
  var currentSort = "latest";

  function qs(name) {
    return new URLSearchParams(window.location.search).get(name);
  }

  function initials(name) {
    return (name || "").split(" ").filter(Boolean).slice(0, 2).map(function (w) { return w[0]; }).join("").toUpperCase();
  }

  function renderHead(author) {
    document.title = author.name + " — " + author.role + ", GameIndo";
    var avatar = document.getElementById("gi-author-avatar");
    if (avatar) avatar.textContent = initials(author.name);
    var role = document.getElementById("gi-author-role");
    if (role) role.textContent = author.role;
    var name = document.getElementById("gi-author-name");
    if (name) name.textContent = author.name;
    var bio = document.getElementById("gi-author-bio");
    if (bio) bio.textContent = author.bio;
    var stats = document.getElementById("gi-author-stats");
    if (stats) {
      stats.innerHTML =
        "<span><b>" + author.articlesCount.toLocaleString("id-ID") + "</b> ARTIKEL</span>" +
        "<span>SEJAK <b>" + author.sinceYear + "</b></span>" +
        "<span><b>" + T.esc(author.monthlyReads) + "</b> DIBACA / BULAN</span>";
    }
  }

  function sortedPosts(sort) {
    var list = authorPosts.slice();
    if (sort === "popular") {
      list.sort(function (a, b) {
        var ra = parseInt(a.reads) || 0, rb = parseInt(b.reads) || 0;
        return rb - ra;
      });
    } else if (sort === "series") {
      list = list.filter(function (p) { return p.subcategory === "MPL ID"; });
    }
    return list;
  }

  function renderGrid(sort) {
    var mount = document.getElementById("gi-author-grid");
    if (!mount) return;
    var list = sortedPosts(sort);
    mount.innerHTML = list.length
      ? list.map(function (p) { return T.card(p, { variant: "md", showAuthor: false }); }).join("")
      : '<p style="color:var(--ink-4);font-size:14px">Belum ada artikel di kategori ini.</p>';
  }

  function renderPopularRail(author) {
    var mount = document.getElementById("gi-author-popular");
    var titleEl = document.getElementById("gi-author-popular-title");
    if (titleEl) titleEl.textContent = "Terpopuler dari " + author.name.split(" ")[0];
    if (!mount) return;
    var top = authorPosts.filter(function (p) { return p.reads; })
      .sort(function (a, b) { return parseInt(b.reads) - parseInt(a.reads); })
      .slice(0, 3);
    mount.innerHTML = top.map(function (p, i) { return T.rankRow(p, i + 1); }).join("");
  }

  function wireTabs() {
    var tabs = document.querySelectorAll("#gi-author-tabs .gi-tab");
    tabs.forEach(function (tab) {
      tab.addEventListener("click", function () {
        tabs.forEach(function (t) { t.removeAttribute("aria-current"); });
        tab.setAttribute("aria-current", "true");
        currentSort = tab.getAttribute("data-sort") || "latest";
        renderGrid(currentSort);
      });
    });
  }

  var slug = qs("slug") || "rizky-pratama";
  Promise.all([CMS.getAuthor(slug), CMS.getAuthorPosts(slug)]).then(function (results) {
    var author = results[0];
    if (!author) {
      var main = document.querySelector("main");
      if (main) main.innerHTML = '<div class="gi-container" style="padding:80px 0;text-align:center"><h1>Penulis tidak ditemukan</h1></div>';
      return;
    }
    authorPosts = results[1];
    renderHead(author);
    renderGrid(currentSort);
    renderPopularRail(author);
    wireTabs();
  }).catch(function (err) {
    console.error("GameIndo: failed to load author page", err);
  });
})();
