/**
 * WordPress taxonomy for gameindo.com.
 *
 * The publisher previously used popshck's category IDs (10-14). Those IDs do
 * not exist on gameindo.com, so WordPress silently filed every one of the
 * first 519 articles under the default category `blog` and nothing was ever
 * categorised as Video Game.
 *
 * Live categories (GET /wp-json/wp/v2/categories):
 *   1 blog | 2 home (Video Game) | 3 esports | 4 streamer | 5 tech | 6 entertainment
 */
import type { Pillar } from '../shared/types';

export const CATEGORY_HOME_VIDEOGAME = 2;
export const CATEGORY_ESPORTS        = 3;
export const CATEGORY_ENTERTAINMENT  = 6;

export const CATEGORY_IDS: Record<Pillar, number> = {
  home:          2,   // Video Game
  esports:       3,
  streamer:      4,
  tech:          5,
  entertainment: 6,
};

export const CATEGORY_LINKS: Record<Pillar, string> = {
  home:          'https://gameindo.com/category/home/',
  esports:       'https://gameindo.com/category/esports/',
  streamer:      'https://gameindo.com/category/streamer/',
  tech:          'https://gameindo.com/category/tech/',
  entertainment: 'https://gameindo.com/category/entertainment/',
};


/**
 * Competitive-play markers, in the three languages the approved Gaming feeds
 * publish in. Used to split the Gaming pillar into Video Game vs Esports.
 *
 * Resolved from the finished article rather than carried down from the Scout:
 * that keeps both publish paths (the pipeline and the dashboard's manual
 * publish button) on one rule, and needs no Prisma migration to store a
 * sub-pillar on every historical row.
 */
/**
 * Category for a finished article.
 *
 * Every GameIndo pillar maps 1:1 to a WordPress category. Esports used to be
 * derived from keywords inside Gaming articles, which produced roughly two
 * posts a day and occasionally miscategorised things (a Dragon Quest
 * anniversary exhibition matched on tournament wording). It is a first-class
 * pillar with its own feeds and Scout quota now, so no keyword split remains.
 */
export function resolveCategoryId(pillar: Pillar, _title = '', _body = ''): number {
  return CATEGORY_IDS[pillar];
}

/** Internal-link target matching the resolved category. */
export function resolveCategoryLink(pillar: Pillar, _title = '', _body = ''): string {
  return CATEGORY_LINKS[pillar];
}
