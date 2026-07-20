# GameIndo → Headless WordPress integration

The site is a static HTML/CSS/JS build (no build step, no framework)
where every piece of content flows through one data layer:

```
data/*.json  ──►  js/cms-client.js  ──►  js/templates.js  ──►  js/pages/*.js  ──►  DOM
(local mock)      (fetch + normalize)     (render as HTML)      (fetch + mount)
```

Every page controller in `js/pages/` calls `CMS.getX()` — never
`fetch('data/...')` directly. That means going live is a config change,
not a rewrite.

> **Writing articles (AI copywriter)?** See
> [ARTICLE-CONTRACT.md](ARTICLE-CONTRACT.md) for the exact HTML/metadata
> an article must supply to render on-brand, and the WordPress REST calls
> to publish one. This doc below is the read path (site ← WordPress);
> that doc is the write path (WordPress ← copywriter).

## 1. Point the frontend at a real WordPress site

Edit `js/config.js`:

```js
window.GI_CONFIG = {
  API_BASE: "https://public-api.wordpress.com/wp/v2/sites/yourslug.wordpress.com",
  // or self-hosted: "https://yourdomain.com/wp-json/wp/v2"
  CUSTOM_API_BASE: "https://yourdomain.com/wp-json/gameindo/v1", // see §3
  LOCAL_DATA_BASE: "data",
  PILLAR_SLUGS: ["home", "esports", "streamer", "tech", "entertainment"]
};
```

`js/cms-client.js` immediately switches from reading `/data/*.json` to
calling the real REST endpoints — `_embed=1` is used on `/posts` so
author + featured image come back in one request, matching the shape
`normalizePost()` already expects.

## 2. Content mapping (standard WordPress, no plugins needed)

| Site concept | WordPress equivalent | Notes |
|---|---|---|
| Post (article) | Post (`wp/v2/posts`) | `title`, `excerpt`, `content`, `date` map directly |
| Pillar (Video Game / Esports / Streamer / Tech / Entertainment) | Category (`wp/v2/categories`) | Category **slugs must be** `home`, `esports`, `streamer`, `tech`, `entertainment` — these drive the `[data-pillar]` CSS theming in `css/tokens/colors.css`. Rename categories in WP, not in the CSS. |
| Sub-topic pill on esports posts (MLBB, MPL ID, Valorant, Free Fire, Transfer) | Tag, surfaced via a custom field | See `gi_meta.subcategory` below |
| Author | User (`wp/v2/users`) | `name`, `description` (bio) map directly |
| Featured image | Featured media (`_embedded['wp:featuredmedia']`) | Requires `_embed=1` on the request |

## 3. Fields with no standard WordPress equivalent

A few things in the design aren't ordinary post content. Cleanest way
to add them: install **Advanced Custom Fields (ACF)** and register a
field group called `gi_meta` on the Post type, plus a tiny custom
plugin (a few lines of `register_rest_route`) for the two liveblog-y
widgets. All are already isolated behind `CMS.getX()` so nothing else
in the codebase needs to change once they're live.

**Per-post ACF fields (`gi_meta`)** — exposed via `register_rest_field`
so they ride along on `wp/v2/posts` responses:
- `pillar` (text, required) — must equal one of `PILLAR_SLUGS`. Redundant with category slug; kept as an explicit field so editors can't accidentally leave a post uncategorized.
- `subcategory` (text) — e.g. "MLBB", "MPL ID", "Valorant" — shown as the pill on esports cards instead of the generic "Esports" label.
- `read_time` (text) — e.g. "4 min read".
- `featured` (true/false) — marks the single homepage hero article.
- `spotlight` (true/false) — marks up to 4 posts for the "Sorotan Malam Ini" rail.
- `reads` (text) — display string like "128 rb", used for the "Terpopuler" rank rails. If you have real analytics, wire this from there instead of hand-entering it.
- `tags` (repeater/text) — hashtag chips shown under the article body.

**Per-user ACF fields (`gi_profile`)** on the User/Author profile:
- `role` (text) — e.g. "Editor Esports".
- `articles_count`, `since_year`, `monthly_reads` — the three stats shown on the author masthead.

**Custom REST namespace** (`gameindo/v1`, matches `CUSTOM_API_BASE`) for
content that changes too often/structurally to be a post:
- `GET /gameindo/v1/ticker` → array of `{ id, text, url }` — the live-feed marquee. Simplest implementation: an ACF Options Page with a repeater field, exposed through a small `register_rest_route` callback.
- `GET /gameindo/v1/matches` → `{ competition, matches: [{ id, status, status_label, team_a, score_a, team_b, score_b }] }` — the homepage score bar. Consider a custom post type (`gi_match`) if match history/archives matter later; a single options-page repeater is enough for "current state".
- `GET /gameindo/v1/standings` → `{ competition, season_label, rows: [{ rank, team, wl, pts, top }] }` — the esports klasemen panel. Same options-page approach works fine.

If `CUSTOM_API_BASE` is left unset, `cms-client.js` automatically keeps
serving these three from `data/ticker.json` / `data/matches.json` /
`data/standings.json` — useful for previewing design changes before
the WP plugin work is done.

## 4. Routing

Static pages read state from the query string instead of WP permalinks,
so no server-side routing/rewrite rules are needed:

- `article.html?slug=<post-slug>` — single post.
- `author.html?slug=<user-slug>` — author archive.
- `search.html?q=<term>` — search results (client-side filtered against `wp/v2/posts?search=` once live).

If you'd rather have real permalinks (`/artikel/<slug>/`), that's a
step up to a server-rendered template (PHP theme or a static-site
generator with a build step) — the current approach intentionally
avoids a build step so it can be dropped into any static host or a
WordPress.com "Custom HTML"-style setup.

## 5. CORS

WordPress.com's public API (`public-api.wordpress.com`) sends
permissive CORS headers already. A self-hosted site needs an
`Access-Control-Allow-Origin` header for the frontend's origin — the
cleanest way is a one-line `add_action('rest_api_init', ...)` snippet
in a small must-use plugin, since editing `.htaccess`/server config
varies by host.

## 6. What stays static (site chrome, not CMS content)

Header nav (5 pillar links), footer, and the mobile drawer are
hand-authored HTML repeated across pages — this is standard practice
for a fixed site IA (WordPress menus are configured once per theme,
not fetched per-request either). Only the *counts* on the homepage
pillar tiles are pulled live from `CMS.getPillars()`.
