# GameIndo → Article contract (for the AI copywriter)

This is the spec your AI copywriter (à la popshck.com) targets so every
generated article drops into gameindo.com **already designed** — no
manual layout, no per-article CSS. It complements
[CMS-INTEGRATION.md](CMS-INTEGRATION.md), which covers the plumbing
between the site and a headless WordPress backend.

The rule: **the copywriter emits an article as a *featured image* + a
*block of clean HTML* + a small set of *metadata fields*.** The site's
prose stylesheet (`css/article.css`, scoped to `.gi-prose`) turns that
HTML into the GameIndo look, and the article re-themes to its pillar's
signature color automatically.

---

## 1. What an article is

| Piece | Where it goes in WordPress | Notes |
|---|---|---|
| **Title** | Post title | Plain text. |
| **Dek / summary** | Post excerpt | 1–2 sentences. Shown under the headline and on cards. |
| **Body** | Post content | The HTML described in §3. This is the main payload. |
| **Featured image** | Featured media (`_embed`) | One 16:9 hero image. Rendered big above the body — **do not** also put it as the first inline image in the body. |
| **Pillar** | Category slug **and** `gi_meta.pillar` | One of `home` (Video Game), `esports`, `streamer`, `tech`, `entertainment`. Drives the accent color. |
| **Subcategory** | `gi_meta.subcategory` | Free text pill, e.g. `MLBB`, `Review`, `Anime`. |
| **Read time / tags / flags** | `gi_meta.*` | See CMS-INTEGRATION.md §3. |

The featured image + all body images live in the **WordPress media
library**; the copywriter uploads them there and references the returned
URLs. The frontend already pulls featured media via `_embed=1`.

---

## 2. The golden rule for body images

> Images placed **inside the body HTML** as `<figure class="wp-block-image">`
> automatically get the same treatment as the hero: full content width,
> 8px rounded corners, lazy-loaded, with a monospace caption. You never
> style them per-article.

That single pattern is what makes "upload an article + images and it's
instantly designed" work.

---

## 3. Supported HTML vocabulary

Everything below is styled on-brand by `.gi-prose`. This is exactly the
markup the **WordPress block editor (Gutenberg) already outputs**, so if
the copywriter authors in WordPress (or posts Gutenberg HTML through the
REST API), it renders correctly with zero extra work. Clean semantic
HTML (`<h2>`, `<ul>`, `<figure>`) works identically.

### Text
```html
<p>Body paragraph. The <strong>first</strong> paragraph is auto-styled as a lead.</p>
<h2>Section heading</h2>          <!-- display face, UPPERCASE italic -->
<h3>Sub-section</h3>
<p>Inline <a href="/some-url">link</a>, <strong>bold</strong>, <em>italic</em>.</p>
```

### Inline image (the important one)
```html
<figure class="wp-block-image size-large">
  <img src="https://cms.gameindo.com/wp-content/uploads/2026/07/shot.jpg" alt="Describe the image">
  <figcaption>Caption text. Source: GameIndo</figcaption>
</figure>
```
Alignment variants (all supported):
- `<figure class="wp-block-image aligncenter">` — centered.
- `class="... alignwide"` — breaks wider than the text column.
- `class="... alignfull"` — full-bleed edge to edge.
- `class="... alignleft"` / `alignright` — text wraps around it (stacks on mobile).

### Lists (get pillar-colored markers)
```html
<ul><li>Bullet one</li><li>Bullet two</li></ul>
<ol><li>Step one</li><li>Step two</li></ol>
```

### Pull-quote / block-quote (pillar-tinted)
```html
<blockquote class="wp-block-quote">
  <p>The sentence you want to pull out and emphasize.</p>
  <cite>Attribution, Role</cite>
</blockquote>
```

### Table (auto scrolls horizontally on mobile)
```html
<figure class="wp-block-table">
  <table>
    <thead><tr><th>Col</th><th>Col</th></tr></thead>
    <tbody><tr><td>x</td><td>y</td></tr></tbody>
  </table>
</figure>
```

### Also styled
- `<hr>` / `.wp-block-separator` — hairline divider.
- `<pre><code>…</code></pre>` and inline `<code>` — dark code block / chip.
- `<figure class="wp-block-embed …">` with an `<iframe>` — responsive 16:9 embed (YouTube, X, etc.).
- `.wp-block-button` → `.wp-block-button__link` — pillar-colored CTA button.

### Do NOT emit
- No inline `style="…"` attributes, `<font>`, `<center>`, or fixed pixel widths — the design system handles sizing.
- No `<h1>` in the body — the post title is the H1.
- No embedded `<script>`.

A complete, working example lives in the repo: open
`article.html?slug=review-lengkap-rog-ally-x2-handheld-terbaik-2026`
(its raw HTML is post id 124 in `data/posts.json`). Use it as the
copywriter's few-shot template.

---

## 4. Publishing via the WordPress REST API

Once `js/config.js` → `API_BASE` points at the live WordPress site
(see CMS-INTEGRATION.md §1), the copywriter publishes like this:

1. **Upload each image** → `POST /wp/v2/media` (multipart). Keep the
   returned `id` and `source_url`.
2. **Create the post** → `POST /wp/v2/posts` with:
   ```jsonc
   {
     "title":    "…",
     "excerpt":  "…",                     // the dek
     "content":  "<p>…</p><figure class=\"wp-block-image\">…",  // §3 HTML, image src = uploaded source_url
     "status":   "publish",               // or "draft" for review
     "categories": [<pillar category id>],
     "featured_media": <media id from step 1>,
     "gi_meta": {                          // ACF fields, exposed via register_rest_field
       "pillar": "tech", "subcategory": "Review",
       "read_time": "7 min read", "tags": ["ROG Ally","Review"]
     }
   }
   ```
3. Done. The frontend fetches it through `CMS.getPosts()` — no rebuild,
   no deploy. The article page resolves it at
   `article.html?slug=<the-new-post-slug>`.

Authentication for the write calls uses an **Application Password** (WP
Users → Profile → Application Passwords) sent as HTTP Basic auth, or a
JWT plugin — this is a server-to-server credential for the copywriter
bot, never exposed to the frontend.

---

## 5. Previewing before WordPress is live

No backend yet? Append a post object to `data/posts.json` in the same
shape as id 124 (title/excerpt/content are `{ "rendered": "…" }`,
featured image under `_embedded["wp:featuredmedia"]`, metadata under
`gi_meta`) and drop its images in `assets/`. The exact same prose
rendering applies, so the copywriter's HTML can be validated visually
before the WordPress plugin work is finished.
