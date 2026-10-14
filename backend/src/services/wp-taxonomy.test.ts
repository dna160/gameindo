import { resolveCategoryId, resolveCategoryLink, CATEGORY_IDS } from './wp-taxonomy';
import { PILLARS, PILLAR_LABELS } from '../shared/types';
import type { Pillar } from '../shared/types';

const NAMES: Record<number, string> = {
  1: 'blog', 2: 'home (Video Game)', 3: 'esports', 4: 'streamer', 5: 'tech', 6: 'entertainment',
};

// Every pillar maps 1:1 to a live gameindo.com category.
const EXPECTED: Record<Pillar, number> = {
  home: 2, esports: 3, streamer: 4, tech: 5, entertainment: 6,
};

let pass = 0, fail = 0;
function check(name: string, got: unknown, want: unknown) {
  const ok = got === want;
  ok ? pass++ : fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name.padEnd(46)} -> ${got}${ok ? '' : `  (expected ${want})`}`);
}

for (const p of PILLARS) {
  check(`${p} -> ${NAMES[EXPECTED[p]]}`, resolveCategoryId(p, 'any title', 'any body'), EXPECTED[p]);
}

// No pillar may collide with another, and none may fall into blog(1).
const ids = PILLARS.map((p) => CATEGORY_IDS[p]);
check('all five categories distinct', new Set(ids).size, 5);
check('none falls back to blog(1)', ids.includes(1), false);

// Esports is decided by the pillar, never by keywords in the copy. A Video
// Game article that merely mentions a tournament must stay in Video Game.
check('tournament wording does not move home -> esports',
  resolveCategoryId('home', 'Update besar untuk PS5', 'Turnamen CS2 tertunda akibat bug.'), 2);
check('esports pillar routes to esports regardless of wording',
  resolveCategoryId('esports', 'Roster shuffle', 'No competitive keywords here at all.'), 3);

check('link(home)',     resolveCategoryLink('home', '', ''),     'https://gameindo.com/category/home/');
check('link(esports)',  resolveCategoryLink('esports', '', ''),  'https://gameindo.com/category/esports/');
check('link(streamer)', resolveCategoryLink('streamer', '', ''), 'https://gameindo.com/category/streamer/');
check('link(tech)',     resolveCategoryLink('tech', '', ''),     'https://gameindo.com/category/tech/');

console.log('\nlabels:', PILLARS.map((p) => `${p}=${PILLAR_LABELS[p]}`).join('  '));
console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail === 0 ? 0 : 1);
