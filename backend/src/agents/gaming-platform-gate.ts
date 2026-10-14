/**
 * Gaming pillar platform gate.
 *
 * The Scout was letting anime coverage through under the Gaming pillar, and
 * accepting game news for platforms this newsroom does not cover (mobile-only,
 * gacha, arcade, browser, retro-only). Prompt wording alone does not hold a
 * small local model to that line, so the rule is enforced in code as well:
 *
 *   1. The triage prompt asks for an explicit `platforms` array.
 *   2. Anything classified Gaming must name at least one COVERED platform.
 *   3. That platform must also be corroborated by the source text, which is
 *      what actually stops an anime item being laundered into Gaming by a
 *      hallucinated "PC".
 *
 * Covered platforms: Nintendo Switch, PlayStation 5, PC, Xbox.
 */

export type CoveredPlatform = 'Nintendo Switch' | 'PlayStation 5' | 'PC' | 'Xbox';

export const COVERED_PLATFORMS: CoveredPlatform[] = [
  'Nintendo Switch',
  'PlayStation 5',
  'PC',
  'Xbox',
];

/**
 * Aliases per platform. ASCII entries are matched on word boundaries (so "pc"
 * does not fire inside "PCR" or "upcoming"); non-ASCII entries are matched as
 * plain substrings, because Japanese text has no word boundaries to anchor to.
 */
const PLATFORM_ALIASES: Record<CoveredPlatform, string[]> = {
  'Nintendo Switch': [
    'nintendo switch', 'switch 2', 'switch2', 'nintendo switch 2', 'nsw',
    // Bare "switch" is the English verb as often as the console, so only
    // accept it in phrasings that are unambiguously about the platform.
    'on switch', 'for switch', 'to switch', 'switch version', 'switch release',
    'switch port', 'switch edition', 'switch 2 version',
    'ニンテンドースイッチ', 'ニンテンドー スイッチ', 'スイッチ',
    // Korean (ruliweb)
    '닌텐도 스위치', '닌텐도스위치', '스위치', '스위치2', '스위치 2', 'NS2',
  ],
  'PlayStation 5': [
    'playstation 5', 'playstation5', 'ps5', 'ps 5',
    'プレイステーション5', 'プレイステーション５', 'プレステ5', 'プレステ５', 'ＰＳ５',
    // Korean (ruliweb)
    '플레이스테이션 5', '플레이스테이션5', '플스5', '플스 5',
  ],
  'PC': [
    'pc', 'steam', 'steam deck', 'epic games store', 'gog', 'windows',
    'pcゲーム', 'ＰＣ', 'スチーム',
    // Korean (ruliweb)
    '스팀', '스팀덱', '피시',
  ],
  'Xbox': [
    'xbox', 'xbox series x', 'xbox series s', 'xbox series x|s',
    'xbox one', 'game pass', 'xbox game pass', 'エックスボックス', 'ゲームパス',
    // Korean (ruliweb)
    '엑스박스', '엑박', '게임패스',
  ],
};

/** Platforms we deliberately do NOT cover, kept for clear rejection reasons. */
const EXCLUDED_HINTS = [
  'ios', 'android', 'mobile', 'smartphone', 'gacha', 'arcade', 'browser',
  // Storefronts name the platform even when the copy never says "mobile".
  'app store', 'google play', 'appstore', 'googleplay', 'play store',
  'アプリストア', '앱스토어', '구글 플레이',
  'ps4', 'playstation 4', 'nintendo 3ds', '3ds', 'wii u', 'vita', 'ps vita',
  'スマホ', 'アプリ', 'アーケード', 'ガチャ',
];

// Escapes every non-word char. Written without backslash literals on purpose.
function escapeRegex(s: string): string {
  return s.replace(/[^A-Za-z0-9_]/g, (c) => String.fromCharCode(92) + c);
}

function isAscii(s: string): boolean {
  return /^[\x00-\x7F]*$/.test(s);
}

/** Does `haystack` actually mention this alias? */
function mentions(haystack: string, alias: string): boolean {
  if (isAscii(alias)) {
    // \b does not work against "|" or trailing punctuation, so bound on
    // non-alphanumerics instead of relying on word-character boundaries.
    const re = new RegExp(`(^|[^a-z0-9])${escapeRegex(alias)}([^a-z0-9]|$)`, 'i');
    return re.test(haystack);
  }
  return haystack.includes(alias);
}

/**
 * Map whatever the LLM emitted onto our canonical platform names.
 * Unknown strings are dropped rather than guessed at.
 */
export function normalizePlatforms(raw: unknown): CoveredPlatform[] {
  const list: string[] = Array.isArray(raw)
    ? raw.map((x) => String(x))
    : typeof raw === 'string'
      ? raw.split(/[,/;]/)
      : [];

  const out = new Set<CoveredPlatform>();
  for (const entry of list) {
    const value = entry.trim().toLowerCase();
    if (!value) continue;
    for (const platform of COVERED_PLATFORMS) {
      if (PLATFORM_ALIASES[platform].some((a) => value === a.toLowerCase() || mentions(value, a))) {
        out.add(platform);
      }
    }
  }
  return [...out];
}

/** Which covered platforms does the source text itself mention? */
export function platformsInText(text: string): CoveredPlatform[] {
  const hay = text.toLowerCase();
  const out = new Set<CoveredPlatform>();
  for (const platform of COVERED_PLATFORMS) {
    if (PLATFORM_ALIASES[platform].some((a) => mentions(hay, a.toLowerCase()))) {
      out.add(platform);
    }
  }
  return [...out];
}

export function excludedPlatformsInText(text: string): string[] {
  const hay = text.toLowerCase();
  return EXCLUDED_HINTS.filter((h) => mentions(hay, h));
}

/**
 * The ONLY domains allowed to produce Gaming articles. Feed `tags` in
 * services/rss.ts do not constrain what the LLM labels an item, so a Gaming
 * classification coming from any other feed in the broad scrape has to be
 * rejected here or it still reaches the pillar.
 *
 * Matched on suffix, so subdomains count: bbs.ruliweb.com passes for
 * ruliweb.com.
 */
export const GAMING_SOURCE_DOMAINS = [
  'game.watch.impress.co.jp',
  'ruliweb.com',
  '4gamer.net',
  // Added with the Esports/Streamer pillars: these are games press, so their
  // Video Game items are legitimate. Their non-game output (TCG, creator
  // drama, hardware) is routed away by the pillar definitions, not by this
  // list. Still excluded: automaton-media, denfaminicogamer, gamespark --
  // the anime/visual-novel-heavy feeds this allowlist was created to stop.
  'dotesports.com',
  'dexerto.com',
  'hltv.org',
];

/**
 * Whether a source needs its platform claim corroborated by the article text.
 *
 * Corroboration exists to catch misclassification, and only mixed-content
 * sources can misclassify: GAME Watch's feed carries anime, capsule toys and
 * even food alongside games, so a platform it claims must be visible in the
 * text. Ruliweb is dedicated game news, where demanding corroboration only
 * produced false negatives -- a real Call of Duty beta story was dropped
 * purely because the Korean copy never spelled out a platform name.
 */
const MIXED_CONTENT_SOURCES = ['game.watch.impress.co.jp'];

function sourceNeedsEvidence(sourceDomain?: string): boolean {
  if (sourceDomain === undefined) return true;          // unknown source: be strict
  const d = sourceDomain.trim().toLowerCase();
  return MIXED_CONTENT_SOURCES.some((m) => d === m || d.endsWith('.' + m));
}

export function isAllowedGamingSource(sourceDomain: string): boolean {
  let d = (sourceDomain || '').trim().toLowerCase();
  if (d.startsWith('www.')) { d = d.slice(4); }
  if (!d) return false;
  return GAMING_SOURCE_DOMAINS.some((allowed) => d === allowed || d.endsWith('.' + allowed));
}

export type GamingKind = 'title' | 'ecosystem' | 'excluded' | 'not-gaming';

/**
 * Industry events and competitive play. When one of these is named, the story
 * is gaming-industry coverage even if the model failed to name a platform --
 * a gamescom or Tokyo Game Show report legitimately spans platforms.
 *
 * Added after a Silent Hill: Townfall gamescom announcement was rejected for
 * "no covered platform": the model had labelled it a title and left platforms
 * empty, so a story that is exactly what this pillar wants was dropped.
 */
const ECOSYSTEM_MARKERS = [
  'gamescom', '게임스컴', 'ゲームスコム',
  'tokyo game show', '東京ゲームショウ', '도쿄 게임쇼', 'tgs',
  'nintendo direct', 'ニンテンドーダイレクト', '닌텐도 다이렉트',
  'state of play', 'ステートオブプレイ', '스테이트 오브 플레이',
  'summer game fest', 'the game awards', 'ゲームアワード', '게임 어워드',
  'steam next fest', 'steam awards',
  'esports', 'e-sports', 'eスポーツ', 'e스포츠', '이스포츠',
];

export function mentionsEcosystemEvent(text: string): boolean {
  const hay = text.toLowerCase();
  return ECOSYSTEM_MARKERS.some((m) => mentions(hay, m.toLowerCase()));
}

export type GateVerdict =
  | { ok: true;  platforms: CoveredPlatform[]; kind: GamingKind }
  | { ok: false; reason: string };

function normalizeKind(raw: unknown): GamingKind | null {
  const v = String(raw == null ? "" : raw).trim().toLowerCase().split(/[^a-z]+/).join("-");
  if (v === 'title' || v === 'ecosystem' || v === 'excluded') return v;
  if (v === 'not-gaming' || v === 'notgaming' || v === 'none') return 'not-gaming';
  return null;
}

/**
 * Decide whether a Gaming-classified item may pass.
 *
 * Set SCOUT_REQUIRE_PLATFORM_EVIDENCE=false to accept the model's claim
 * without corroboration from the source text (looser, more false positives).
 */
export function checkGamingGate(
  claimed: unknown,
  title: string,
  summary: string,
  sourceDomain?: string,
  claimedKind?: unknown,
): GateVerdict {
  // Both Scout and UnderquotaProtocol call this with identical arguments and
  // get identical verdicts. The rules are deliberately symmetric: making one
  // path stricter only moved disagreement around instead of fixing labelling.
  const requireEvidence =
    process.env.SCOUT_REQUIRE_PLATFORM_EVIDENCE !== 'false' && sourceNeedsEvidence(sourceDomain);
  const text = `${title} ${summary}`;

  // 1. Source: an absolute editorial rule, and the cheapest check.
  if (sourceDomain !== undefined && !isAllowedGamingSource(sourceDomain)) {
    return {
      ok: false,
      reason: `Gaming gate: source "${sourceDomain}" is not an approved Gaming domain ` +
              `(allowed: ${GAMING_SOURCE_DOMAINS.join(', ')})`,
    };
  }

  // 2. What kind of gaming content is it? Anime/toys/music from a games outlet
  //    are still not gaming; mobile-only and arcade-only are out of scope.
  //    An older model that omits gaming_kind falls back to 'title', which is
  //    the strict path -- never the permissive one.
  let kind = normalizeKind(claimedKind) ?? 'title';

  // The model routinely labels event coverage as a "title" and then leaves
  // platforms empty, which would fail the strict title path. If the item names
  // an industry event or esports, treat it as ecosystem -- that is what it is.
  if (kind === 'title' && normalizePlatforms(claimed).length === 0 && mentionsEcosystemEvent(text)) {
    kind = 'ecosystem';
  }
  if (kind === 'not-gaming') {
    return { ok: false, reason: 'Gaming gate: item is not video game content (gaming_kind=not-gaming)' };
  }
  if (kind === 'excluded') {
    return {
      ok: false,
      reason: 'Gaming gate: mobile-only / gacha / arcade / legacy-hardware title (gaming_kind=excluded)',
    };
  }

  const claimedPlatforms = normalizePlatforms(claimed);

  // 3. Ecosystem content -- an expo, an interview, esports, hardware -- is
  //    legitimately platform-spanning, so it does NOT need a named platform.
  //    Requiring one here was rejecting real coverage such as a gamescom
  //    interview, which is exactly the kind of story this pillar wants.
  if (kind === 'ecosystem') {
    const excluded = excludedPlatformsInText(text);
    const evidenced = platformsInText(text);
    if (evidenced.length === 0 && excluded.length > 0) {
      return {
        ok: false,
        reason: `Gaming gate: ecosystem item mentions only out-of-scope platforms (${excluded.join(', ')})`,
      };
    }
    return { ok: true, platforms: claimedPlatforms.length ? claimedPlatforms : evidenced, kind };
  }

  // 4. A specific title must be on a covered platform.
  //
  // Exception: on a dedicated games outlet, an item the model already declined
  // to mark "excluded" is a covered-platform game whose blurb simply never
  // spells the platform out -- a Street Fighter 6 character announcement from
  // 4Gamer was being dropped for exactly that. Mixed-content sources stay
  // strict, because there a missing platform is the anime-leak signal.
  if (claimedPlatforms.length === 0 && !sourceNeedsEvidence(sourceDomain) && sourceDomain !== undefined) {
    // ...but only when the copy does not name an out-of-scope platform. A
    // Shangri-La Frontier pre-registration on "App Store / Google Play" came
    // through as Video Game because the model failed to mark it excluded and
    // this branch trusted the source blindly. The text said mobile; believe it.
    const excluded = excludedPlatformsInText(text);
    const evidenced = platformsInText(text);
    if (excluded.length > 0 && evidenced.length === 0) {
      return {
        ok: false,
        reason: `Gaming gate: out-of-scope platform only (${excluded.join(', ')}) — no Switch/PS5/PC/Xbox`,
      };
    }
    return { ok: true, platforms: evidenced, kind };
  }

  if (claimedPlatforms.length === 0) {
    const excluded = excludedPlatformsInText(text);
    const detail = excluded.length ? ` (source mentions only: ${excluded.join(', ')})` : '';
    return {
      ok: false,
      reason: `Gaming gate: no covered platform (need Nintendo Switch / PlayStation 5 / PC / Xbox)${detail}`,
    };
  }

  if (!requireEvidence) return { ok: true, platforms: claimedPlatforms, kind };

  const evidenced = platformsInText(text);
  const confirmed = claimedPlatforms.filter((p) => evidenced.includes(p));
  if (confirmed.length === 0) {
    return {
      ok: false,
      reason:
        `Gaming gate: claimed ${claimedPlatforms.join(', ')} but the source text ` +
        `mentions no covered platform (likely misclassified non-game news)`,
    };
  }

  return { ok: true, platforms: confirmed, kind };
}

/**
 * Shared prompt text. Both scouts embed this so the Gaming rule cannot drift
 * between the main triage and the underquota "sniper" triage.
 */
export const GAMING_PILLAR_PROMPT = `**Japanese Gaming** — video game content tied to **Nintendo Switch (incl.
     Switch 2), PlayStation 5, PC (Steam/Epic/GOG/Windows), or Xbox**.
     This INCLUDES, and you should ACCEPT:
       - specific games: announcements, release dates, trailers, updates, DLC,
         reviews, ports, remasters
       - the industry around those platforms: trade shows and expos
         (gamescom, Tokyo Game Show, State of Play, Nintendo Direct),
         publisher/studio news, developer interviews, awards and nominations
       - esports and competitive play on those platforms
       - console/PC gaming hardware and accessories, storefront and sales news
     REJECT (not Gaming) if the item is:
       - anime, manga, a figure/toy, music, live-action film/TV, or any other
         merchandise or media story — even when published by a games outlet,
         and even when it involves a game franchise
       - a game that is mobile-only / smartphone / gacha-only, arcade-only, or
         browser-only
       - only for legacy hardware (PS4, 3DS, Wii U, PS Vita) with no
         Switch / PS5 / PC / Xbox version`;

export const PLATFORMS_FIELD_PROMPT = `   For the Gaming pillar you MUST also return "gaming_kind" and "platforms".

   "gaming_kind" — exactly one of:
     "title"     — about a SPECIFIC game on Switch / PlayStation 5 / PC / Xbox
     "ecosystem" — video game industry content for those platforms: expos and
                   trade shows, publisher/studio news, developer interviews,
                   awards, esports, gaming hardware and accessories, storefront
                   and sales news
     "excluded"  — a game that is mobile-only, gacha-only, arcade-only,
                   browser-only, or legacy-hardware-only
     "not-gaming"— anime, manga, figures/toys, music, live-action, food, or any
                   other non-game story, even from a games outlet

   "platforms" — array naming the covered platforms, using exactly these
   spellings: "Nintendo Switch", "PlayStation 5", "PC", "Xbox".
     - REQUIRED when gaming_kind is "title": name only platforms the item
       states, or that you actually know for that game. Never guess, and never
       list all four to be safe — a wrong platform gets the item rejected.
     - MAY be empty when gaming_kind is "ecosystem" (an expo or an interview
       often spans platforms). Leave it empty rather than inventing entries.
     - Always empty for every non-Gaming pillar: "platforms": []`;
