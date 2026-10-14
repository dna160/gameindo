import Parser from 'rss-parser';
import type { Pillar } from '../shared/types';

const parser = new Parser({
  timeout: 10000,
  headers: {
    'User-Agent': 'Mozilla/5.0 (compatible; SyntheticNewsroom/1.0; +https://github.com/dna160/popshckv3)',
  },
});

export interface RssItem {
  title: string;
  link: string;
  summary: string;
  pubDate?: string;
  pillar: Pillar;
  sourceFeed: string; // hostname of the feed URL this item came from
}

/**
 * A feed entry with explicit pillar affinity tags, confidence rating, and
 * optional fallback URL.
 *
 * Tags reflect the pillars this feed PREDOMINANTLY covers based on actual
 * historical output (feed-memory.json), not just the publication's stated
 * coverage. If a feed claims to cover infotainment but FeedMemory shows zero
 * infotainment items in 30+ samples, the infotainment tag is removed.
 *
 * Confidence levels:
 *   • 'high'       — proven feed: ≥30 historical items, consistently produces
 *                    items for its tagged pillars. Underquota Protocol drains
 *                    these first.
 *   • 'medium'     — proven feed: 5–29 historical items, lower volume but
 *                    reliable.
 *   • 'low'        — proven but rare-yield: <5 historical items.
 *   • 'unverified' — configured but never recorded items in FeedMemory.
 *                    Either the URL is broken, the LLM rejects all items, or
 *                    items get attributed to a Mastodon proxy. Drained LAST
 *                    so verified sources are exhausted first.
 */
export interface FeedConfig {
  url:        string;
  tags:       Pillar[];
  confidence: 'high' | 'medium' | 'low' | 'unverified';
  fallback?:  string;
}

/**
 * ── Tier 2: Preferred — General Feeds (Round 1 / Broad Scrape) ───────────────
 *
 * Fetched on every Round 1 dispatch. Mixed-topic, high-volume Japanese
 * pop-culture feeds. The Scout's LLM triage categorises each item into the
 * correct pillar. These feeds organically cover all 5 pillars but are not
 * specialised — they are the starting "broad net".
 *
 * ── Tier 1: Priority — Subpillar-Specific Feeds (Underquota Protocol) ─────────
 *
 * Entries with a single specific tag (e.g. tags: ['streamer']) are subpillar
 * branches. The Underquota Protocol filters this list by tag to build a
 * targeted pool for exactly the deficit pillar(s).
 *
 * e.g. natalie.mu/comic → tagged ['streamer'] → activated when manga is underquota
 */
export const PRIORITY_FEEDS: FeedConfig[] = [
  // ─────────────────────────────────────────────────────────────────────────
  //   Tags are the pillars a feed may supply. They steer which feeds the
  //   Underquota Protocol fetches for a deficit pillar -- they do NOT
  //   constrain what the LLM labels an item, so per-pillar scoping is
  //   enforced in the triage prompt and in the gates.
  // ─────────────────────────────────────────────────────────────────────────

  // ── Video Game ──────────────────────────────────────────────────────────
  { url: 'https://www.4gamer.net/rss/index.xml',                        tags: ['home', 'esports', 'entertainment'], confidence: 'high' },
  { url: 'https://game.watch.impress.co.jp/data/rss/1.0/gmw/feed.rdf',  tags: ['home', 'entertainment'],            confidence: 'high' },
  { url: 'https://bbs.ruliweb.com/news/rss',                            tags: ['home', 'esports'],                  confidence: 'high' },
  { url: 'https://bbs.ruliweb.com/news/board/1001/rss',                 tags: ['home'],                             confidence: 'high' },

  // ── Esports ─────────────────────────────────────────────────────────────
  { url: 'https://dotesports.com/feed',                                 tags: ['esports', 'home'],                  confidence: 'medium' },
  { url: 'https://www.hltv.org/rss/news',                               tags: ['esports'],                          confidence: 'high' },
  { url: 'https://www.dexerto.com/feed/',                               tags: ['esports', 'streamer', 'home'],      confidence: 'medium' },

  // ── Streamer / VTuber ───────────────────────────────────────────────────
  { url: 'https://www.moguravr.com/feed/',                              tags: ['streamer'],                         confidence: 'high' },
  { url: 'https://vtuber-post.com/feed',                                tags: ['streamer'],                         confidence: 'unverified' },

  // ── Tech ────────────────────────────────────────────────────────────────
  { url: 'https://pc.watch.impress.co.jp/data/rss/1.0/pcw/feed.rdf',    tags: ['tech'],                             confidence: 'high' },
  { url: 'https://k-tai.watch.impress.co.jp/data/rss/1.0/ktw/feed.rdf', tags: ['tech'],                             confidence: 'high' },
  { url: 'https://av.watch.impress.co.jp/data/rss/1.0/avw/feed.rdf',    tags: ['tech'],                             confidence: 'high' },
  { url: 'https://bbs.ruliweb.com/news/board/1003/rss',                 tags: ['tech'],                             confidence: 'medium' },
  { url: 'https://www.gsmarena.com/rss-news-reviews.php3',              tags: ['tech'],                             confidence: 'medium' },

  // ── Entertainment ───────────────────────────────────────────────────────
  { url: 'https://www.animenewsnetwork.com/all/rss.xml?ann-edition=us', tags: ['entertainment'],                    confidence: 'high' },
  { url: 'https://animecorner.me/feed/',                                tags: ['entertainment'],                    confidence: 'high' },
  { url: 'https://chaosphere.hostdon.jp/@natalie.rss',                  tags: ['entertainment'],                    confidence: 'high' },
  { url: 'https://rss-mstdn.studiofreesia.com/@animeanime.rss',         tags: ['entertainment'],                    confidence: 'medium' },
  { url: 'https://rss-mstdn.studiofreesia.com/@oricon_news.rss',        tags: ['entertainment'],                    confidence: 'medium' },
  { url: 'https://figsoku.net/feed',                                    tags: ['entertainment'],                    confidence: 'high' },
  { url: 'https://hobby.dengeki.com/feed/',                             tags: ['entertainment'],                    confidence: 'high' },
  { url: 'https://bbs.ruliweb.com/news/board/1002/rss',                 tags: ['entertainment'],                    confidence: 'medium' },
];

/**
 * ── Tier 1: Priority — Subpillar Branch Feeds (Underquota Protocol) ──────────
 *
 * Activated ONLY when the Master Orchestrator detects a quota deficit after
 * Round 1. The Scout switches from the broad net to a "sniper" approach,
 * fetching exclusively from hyper-specific feeds that match the missing pillars.
 *
 * Rules:
 *   - Only feeds for the missing_pillars are fetched; others are ignored.
 *   - LLM triage strictly filters results to the target pillar(s) only.
 *   - Pool size: 50 items (RETRY_POOL_SIZE) per dispatch.
 *
 * Tier 3 (fallback_protocol) re-uses these same feeds but sweeps ALL pillars,
 * sorted by FeedMemory score, when both Round 1 and Underquota have failed.
 *
 * Feed sources match the README Content Pillars table (source of truth).
 */
export const RSS_FEEDS: Record<Pillar, string[]> = {
  // ── Video Game ──────────────────────────────────────────────────────────
  // Restricted to the approved Gaming domains; see GAMING_SOURCE_DOMAINS in
  // agents/gaming-platform-gate.ts. game.watch also carries anime/toy/food
  // items, which the platform gate filters out.
  home: [
    'https://www.4gamer.net/rss/index.xml',
    'https://game.watch.impress.co.jp/data/rss/1.0/gmw/feed.rdf',
    'https://bbs.ruliweb.com/news/rss',
    'https://bbs.ruliweb.com/news/board/1001/rss',
  ],

  // ── Esports ─────────────────────────────────────────────────────────────
  // Thinner than the others: oneesports and esports.id have no working RSS,
  // esportsinsider fails to respond, and HLTV is Counter-Strike only. Dot
  // Esports and Dexerto both mix in general gaming, so the triage prompt has
  // to do real work here.
  esports: [
    'https://dotesports.com/feed',
    'https://www.hltv.org/rss/news',
    'https://www.dexerto.com/feed/',
  ],

  // ── Streamer / VTuber ───────────────────────────────────────────────────
  streamer: [
    'https://www.moguravr.com/feed/',
    'https://www.dexerto.com/feed/',
    'https://vtuber-post.com/feed',
  ],

  // ── Tech ────────────────────────────────────────────────────────────────
  // Impress's three hardware desks plus a Korean AI/tech board and GSMArena.
  tech: [
    'https://pc.watch.impress.co.jp/data/rss/1.0/pcw/feed.rdf',
    'https://k-tai.watch.impress.co.jp/data/rss/1.0/ktw/feed.rdf',
    'https://av.watch.impress.co.jp/data/rss/1.0/avw/feed.rdf',
    'https://bbs.ruliweb.com/news/board/1003/rss',
    'https://www.gsmarena.com/rss-news-reviews.php3',
  ],

  // ── Entertainment ───────────────────────────────────────────────────────
  // Anime, manga, figures, music, live-action — everything that used to be
  // four separate popshck pillars.
  entertainment: [
    'https://www.animenewsnetwork.com/all/rss.xml?ann-edition=us',
    'https://animecorner.me/feed/',
    'https://chaosphere.hostdon.jp/@natalie.rss',
    'https://rss-mstdn.studiofreesia.com/@animeanime.rss',
    'https://rss-mstdn.studiofreesia.com/@oricon_news.rss',
    'https://figsoku.net/feed',
    'https://hobby.dengeki.com/feed/',
    'https://bbs.ruliweb.com/news/board/1002/rss',
  ],
};

/**
 * Lookup map: primary feed URL → fallback URL.
 * Built automatically from PRIORITY_FEEDS so callers don't have to scan the array.
 */
export const FEED_FALLBACK_MAP: ReadonlyMap<string, string> = new Map(
  PRIORITY_FEEDS
    .filter((f): f is FeedConfig & { fallback: string } => Boolean(f.fallback))
    .map((f) => [f.url, f.fallback])
);

// ─────────────────────────────────────────────────────────────────────────────
// Internal helpers
// ─────────────────────────────────────────────────────────────────────────────

function isMastodonUrl(url: string): boolean {
  return url.includes('hostdon.jp') || url.includes('mastodon') || url.includes('studiofreesia.com');
}

/**
 * For Mastodon-proxy feeds (e.g. Natalie via chaosphere.hostdon.jp or
 * rss-mstdn.studiofreesia.com), items have no <title>. Extract a title and
 * the real article URL from the HTML description instead.
 */
function extractFromMastodonDescription(
  html: string,
  fallbackLink: string
): { title: string; link: string } {
  // Strip HTML tags
  const text = html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
  // Remove leading 【 #tag #tag 】 section
  const cleaned = text.replace(/^【[^】]*】\s*/, '').trim();
  // Find the first real article URL embedded in an <a href> (exclude proxy domains)
  const urlMatch = html.match(/href="(https?:\/\/(?!chaosphere)(?!rss-mstdn)[^"]+)"/);
  const articleLink = urlMatch ? urlMatch[1] : fallbackLink;
  // Title is everything before the URL at the end of the cleaned text
  const title = cleaned.replace(/https?:\/\/\S+/g, '').trim() || cleaned.slice(0, 120);
  return { title, link: articleLink };
}

/**
 * Attempt to parse a single URL. Returns items on success, throws on failure.
 */
async function parseUrl(url: string, pillar: Pillar): Promise<RssItem[]> {
  let sourceFeed = url;
  try { sourceFeed = new URL(url).hostname; } catch { /* keep raw url */ }

  const feed = await parser.parseURL(url);
  const isMastodon = isMastodonUrl(url);

  return (feed.items || [])
    .filter((item) => item.link || item.guid)
    .map((item) => {
      const rawLink = (item.link || item.guid || '').trim();

      // Mastodon-proxy items lack <title> — extract from description HTML
      if (isMastodon && !item.title) {
        const html = item.content || item.summary || item['content:encoded'] || '';
        const { title, link } = extractFromMastodonDescription(html, rawLink);
        return { title, link, summary: title, pubDate: item.pubDate, pillar, sourceFeed };
      }

      if (!item.title) return null;
      return {
        title: item.title.trim(),
        link: rawLink,
        summary: item.contentSnippet || item.summary || item.content || '',
        pubDate: item.pubDate,
        pillar,
        sourceFeed,
      };
    })
    .filter((item): item is NonNullable<typeof item> => item !== null && item.title.length > 0 && item.link.length > 0) as RssItem[];
}

/**
 * Fetch and parse a single RSS feed URL.
 * If the primary URL fails and a fallback is provided, the fallback is tried.
 * Returns array of RssItems (may be empty if both fail).
 */
export async function fetchFeed(url: string, pillar: Pillar, fallback?: string): Promise<RssItem[]> {
  try {
    return await parseUrl(url, pillar);
  } catch (err) {
    console.warn(`[RSS] Failed to fetch ${url}:`, (err as Error).message);

    if (fallback) {
      console.info(`[RSS] Trying fallback for ${url} → ${fallback}`);
      try {
        return await parseUrl(fallback, pillar);
      } catch (fbErr) {
        console.warn(`[RSS] Fallback also failed for ${fallback}:`, (fbErr as Error).message);
      }
    }

    return [];
  }
}

/**
 * Fetch the Tier 1 (Priority / Subpillar) feeds for a given pillar.
 * Uses RSS_FEEDS[pillar] — the hyper-specific subpillar branches, NOT the
 * general PRIORITY_FEEDS (Tier 2). Called during Underquota Protocol.
 */
export async function fetchPillarFeeds(pillar: Pillar): Promise<RssItem[]> {
  const feedUrls = RSS_FEEDS[pillar] ?? [];
  const results  = await Promise.allSettled(
    feedUrls.map((url) => fetchFeed(url, pillar))
  );

  const allItems: RssItem[] = [];
  const seenLinks = new Set<string>();

  for (const result of results) {
    if (result.status === 'fulfilled') {
      for (const item of result.value) {
        if (!seenLinks.has(item.link)) {
          seenLinks.add(item.link);
          allItems.push(item);
        }
      }
    }
  }

  return allItems;
}

/**
 * Fetch all feeds for all pillars in parallel.
 */
export async function fetchAllFeeds(): Promise<Record<Pillar, RssItem[]>> {
  const pillars: Pillar[] = ['entertainment', 'home', 'esports', 'streamer', 'tech'];

  const results = await Promise.allSettled(
    pillars.map((p) => fetchPillarFeeds(p).then((items) => ({ pillar: p, items })))
  );

  const output: Partial<Record<Pillar, RssItem[]>> = {};

  for (const result of results) {
    if (result.status === 'fulfilled') {
      output[result.value.pillar] = result.value.items;
    } else {
      // Fill with empty on failure — scout will handle quota
      console.warn('[RSS] Failed to fetch pillar feeds:', result.reason);
    }
  }

  // Ensure all pillars present
  for (const pillar of pillars) {
    if (!output[pillar]) {
      output[pillar] = [];
    }
  }

  return output as Record<Pillar, RssItem[]>;
}
