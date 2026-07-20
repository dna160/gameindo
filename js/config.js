/* ============================================================
   GAMEINDO — CMS configuration.
   Flip API_BASE (and CUSTOM_API_BASE) to a live WordPress site to
   go from local mock data to a real headless WordPress backend
   with zero changes to any HTML/CSS/template code. See
   CMS-INTEGRATION.md for the full mapping and setup steps.
   ============================================================ */
window.GI_CONFIG = {
  // WordPress REST API v2 base. Leave null to use the local JSON
  // fixtures in /data instead. Examples:
  //   WordPress.com:  "https://public-api.wordpress.com/wp/v2/sites/yourslug.wordpress.com"
  //   Self-hosted:    "https://yourdomain.com/wp-json/wp/v2"
  API_BASE: null,

  // Base for the small custom REST namespace that serves content with
  // no standard WP equivalent (live ticker, match center, standings).
  // e.g. "https://yourdomain.com/wp-json/gameindo/v1"
  CUSTOM_API_BASE: null,

  // Local fallback data, used whenever API_BASE/CUSTOM_API_BASE are
  // unset or a live request fails.
  LOCAL_DATA_BASE: "data",

  // Pillar (WP category) slugs, in nav order. Must stay in sync with
  // the [data-pillar] theming in css/tokens/colors.css.
  PILLAR_SLUGS: ["home", "esports", "streamer", "tech", "entertainment"]
};
