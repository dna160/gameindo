/* ============================================================
   GAMEINDO — CMS data client.
   One place that knows how to fetch content, whether it's coming
   from the local JSON fixtures (/data) or a live WordPress REST
   API. Every page controller in /js/pages/*.js goes through this
   — never fetches /data/*.json directly — so pointing config.js
   at a real site is the only change needed to go live.
   ============================================================ */
(function (global) {
  "use strict";
  var cfg = global.GI_CONFIG || {};
  var apiBase = cfg.API_BASE;
  var customBase = cfg.CUSTOM_API_BASE;
  var localBase = cfg.LOCAL_DATA_BASE || "data";

  function localJSON(file) {
    return fetch(localBase + "/" + file).then(function (r) {
      if (!r.ok) throw new Error("local fetch failed: " + file);
      return r.json();
    });
  }
  function wpJSON(path) {
    return fetch(apiBase + path).then(function (r) {
      if (!r.ok) throw new Error("wp fetch failed: " + path);
      return r.json();
    });
  }
  function customJSON(path, fallbackFile) {
    if (!customBase) return localJSON(fallbackFile);
    return fetch(customBase + path).then(function (r) {
      if (!r.ok) throw new Error("custom fetch failed: " + path);
      return r.json();
    }).catch(function () { return localJSON(fallbackFile); });
  }
  function stripTags(html) {
    return (html || "").replace(/<[^>]*>/g, "").trim();
  }

  // Raw WP REST post (or local mock, same shape) -> flat view model
  // used by every render function in js/templates.js.
  function normalizePost(p) {
    var embedded = p._embedded || {};
    var author = (embedded.author && embedded.author[0]) || {};
    var media = (embedded["wp:featuredmedia"] && embedded["wp:featuredmedia"][0]) || {};
    var meta = p.gi_meta || {};
    return {
      id: p.id,
      slug: p.slug,
      date: p.date,
      title: p.title.rendered,
      excerpt: stripTags(p.excerpt.rendered),
      content: p.content ? p.content.rendered : "",
      pillar: meta.pillar || "home",
      subcategory: meta.subcategory || "",
      readTime: meta.read_time || "",
      featured: !!meta.featured,
      spotlight: !!meta.spotlight,
      reads: meta.reads || null,
      tags: meta.tags || [],
      author: { id: author.id, slug: author.slug, name: author.name || "GameIndo" },
      image: media.source_url || "",
      imageAlt: media.alt_text || "",
      caption: media.caption ? stripTags(media.caption.rendered) : ""
    };
  }

  function normalizeAuthor(a) {
    var profile = a.gi_profile || {};
    return {
      id: a.id,
      slug: a.slug,
      name: a.name,
      bio: a.description || "",
      role: profile.role || "Kontributor",
      articlesCount: profile.articles_count || 0,
      sinceYear: profile.since_year || "",
      monthlyReads: profile.monthly_reads || ""
    };
  }

  var postCache = null;
  function loadAllPosts() {
    if (postCache) return postCache;
    var raw = apiBase
      ? wpJSON("/posts?_embed=1&per_page=100")
      : localJSON("posts.json");
    postCache = raw.then(function (list) { return list.map(normalizePost); });
    return postCache;
  }

  var CMS = {
    getPillars: function () {
      if (apiBase) {
        return wpJSON("/categories?per_page=50").then(function (cats) {
          return cats.map(function (c) { return { id: c.id, slug: c.slug, name: c.name, count: c.count }; });
        });
      }
      return localJSON("pillars.json");
    },

    // opts: { pillar, search, page, perPage, excludeSlug }
    getPosts: function (opts) {
      opts = opts || {};
      return loadAllPosts().then(function (all) {
        var posts = all.slice();
        if (opts.pillar) posts = posts.filter(function (p) { return p.pillar === opts.pillar; });
        if (opts.search) {
          var q = opts.search.toLowerCase();
          posts = posts.filter(function (p) {
            return p.title.toLowerCase().indexOf(q) > -1 || p.excerpt.toLowerCase().indexOf(q) > -1;
          });
        }
        if (opts.excludeSlug) posts = posts.filter(function (p) { return p.slug !== opts.excludeSlug; });
        posts.sort(function (a, b) { return new Date(b.date) - new Date(a.date); });
        var page = opts.page || 1;
        var perPage = opts.perPage || posts.length;
        return {
          items: posts.slice((page - 1) * perPage, page * perPage),
          total: posts.length,
          page: page,
          perPage: perPage
        };
      });
    },

    getPost: function (slug) {
      return loadAllPosts().then(function (all) {
        return all.find(function (p) { return p.slug === slug; }) || null;
      });
    },

    getAuthors: function () {
      var raw = apiBase ? wpJSON("/users?per_page=50") : localJSON("authors.json");
      return raw.then(function (list) { return list.map(normalizeAuthor); });
    },

    getAuthor: function (slug) {
      return this.getAuthors().then(function (authors) {
        return authors.find(function (a) { return a.slug === slug; }) || null;
      });
    },

    getAuthorPosts: function (authorSlug) {
      return loadAllPosts().then(function (all) {
        return all.filter(function (p) { return p.author.slug === authorSlug; })
          .sort(function (a, b) { return new Date(b.date) - new Date(a.date); });
      });
    },

    // Ticker / match center / standings have no standard WP equivalent —
    // see CMS-INTEGRATION.md for the custom REST route or ACF options
    // page needed to serve these from a live WordPress install.
    getTicker: function () { return customJSON("/ticker", "ticker.json"); },
    getMatches: function () { return customJSON("/matches", "matches.json"); },
    getStandings: function () { return customJSON("/standings", "standings.json"); },

    // "Topik Hangat" hot-topics bar — trending search terms, not post content.
    getTopics: function () { return customJSON("/topics", "topics.json"); }
  };

  global.CMS = CMS;
})(window);
