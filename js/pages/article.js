/* GameIndo — single article page controller. Reads ?slug= from the URL,
   mirroring how a WP single-post permalink/template would resolve the post. */
(function () {
  "use strict";
  var T = window.GITemplates;

  function qs(name) {
    return new URLSearchParams(window.location.search).get(name);
  }

  function renderNotFound() {
    var main = document.querySelector("main");
    if (main) main.innerHTML = '<div class="gi-container" style="padding:80px 0;text-align:center">' +
      '<h1>Artikel tidak ditemukan</h1><p><a href="index.html">&larr; Kembali ke beranda</a></p></div>';
  }

  function renderArticle(post) {
    document.title = post.title + " — GameIndo";

    // Re-theme the whole article to its pillar's signature color, so the
    // pill, pull-quotes, list markers and links all pick up the right
    // accent via [data-pillar] (css/tokens/colors.css) instead of the
    // static "home" red baked into the HTML.
    var articleEl = document.querySelector("article[data-pillar]");
    if (articleEl) articleEl.dataset.pillar = post.pillar;

    var pillEl = document.getElementById("gi-article-pill");
    if (pillEl) pillEl.textContent = T.pillarName(post.pillar);
    var kickerMeta = document.getElementById("gi-article-kicker-meta");
    if (kickerMeta) kickerMeta.textContent = T.fmtDate(post.date).toUpperCase();
    var titleEl = document.getElementById("gi-article-title");
    if (titleEl) titleEl.textContent = post.title;
    var dekEl = document.getElementById("gi-article-dek");
    if (dekEl) dekEl.textContent = post.excerpt;

    var authorLink = document.getElementById("gi-article-author");
    if (authorLink) {
      authorLink.textContent = post.author.name;
      authorLink.href = post.author.slug ? "author.html?slug=" + encodeURIComponent(post.author.slug) : "#";
    }
    var subEl = document.getElementById("gi-article-sub");
    if (subEl) {
      subEl.innerHTML = "<span aria-hidden=\"true\">·</span><span>" + T.fmtDate(post.date) + "</span>" +
        (post.readTime ? "<span aria-hidden=\"true\">·</span><span>" + T.esc(post.readTime) + "</span>" : "");
    }

    var figure = document.getElementById("gi-article-media");
    if (figure) {
      figure.innerHTML = '<img src="' + post.image + '" alt="' + T.esc(post.imageAlt) + '">' +
        (post.caption ? "<figcaption>" + T.esc(post.caption) + "</figcaption>" : "");
    }

    var body = document.getElementById("gi-article-body");
    if (body) body.innerHTML = post.content;

    var tagsEl = document.getElementById("gi-article-tags");
    if (tagsEl && post.tags && post.tags.length) {
      tagsEl.innerHTML = post.tags.map(function (t) {
        return T.tag(t, "search.html?q=" + encodeURIComponent(t));
      }).join("");
    }
  }

  function renderRelated(posts) {
    var mount = document.getElementById("gi-related-grid");
    if (!mount) return;
    mount.innerHTML = posts.map(function (p) { return T.card(p, { variant: "sm" }); }).join("");
  }

  var slug = qs("slug");
  if (!slug) {
    // No slug given (e.g. direct nav from a static link) — fall back to
    // the most recently featured post so the page never renders empty.
    CMS.getPosts({}).then(function (res) {
      var fallback = res.items.find(function (p) { return p.featured; }) || res.items[0];
      if (fallback) window.location.replace("article.html?slug=" + encodeURIComponent(fallback.slug));
      else renderNotFound();
    });
  } else {
    CMS.getPost(slug).then(function (post) {
      if (!post) { renderNotFound(); return; }
      renderArticle(post);
      return CMS.getPosts({ pillar: post.pillar, excludeSlug: post.slug, perPage: 3 });
    }).then(function (related) {
      if (related) renderRelated(related.items);
    }).catch(function (err) {
      console.error("GameIndo: failed to load article", err);
      renderNotFound();
    });
  }
})();
