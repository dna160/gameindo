import {
  checkGamingGate, normalizePlatforms, platformsInText, isAllowedGamingSource,
} from './gaming-platform-gate';

type Case = {
  name: string; kind: string; claimed: unknown;
  title: string; summary: string; src: string; expect: boolean;
};

const GW = 'game.watch.impress.co.jp';   // mixed feed: games + anime + toys + food
const RW = 'bbs.ruliweb.com';            // dedicated game news (Korean)
const FG = 'www.4gamer.net';             // dedicated game news (Japanese)

const cases: Case[] = [
  // ── ecosystem content IS gaming (the gamescom correction) ───────────────
  { name: 'gamescom interview', kind: 'ecosystem', claimed: [], src: RW,
    title: '[GC] 독일 게임쇼가 세계를 대표하기까지, 게임스컴 인터뷰',
    summary: '게임스컴을 세계 최대 게임쇼로 만든 배경을 인터뷰했다.', expect: true },
  { name: 'TGS exhibit', kind: 'ecosystem', claimed: ['Nintendo Switch'], src: FG,
    title: '「ペルソナ4 リバイバル」，東京ゲームショウ2026に試遊出展決定',
    summary: 'Switch2版も試遊できる。', expect: true },
  { name: 'esports final', kind: 'ecosystem', claimed: [], src: FG,
    title: 'Esports World Cup 2026, AG.AL wins club championship',
    summary: 'The PC shooter league concluded its season.', expect: true },
  { name: 'dev interview', kind: 'ecosystem', claimed: [], src: FG,
    title: 'Interview with the director of a PS5 action title',
    summary: 'He discussed the studio roadmap.', expect: true },
  { name: 'gaming accessory', kind: 'ecosystem', claimed: ['Nintendo Switch'], src: GW,
    title: 'Pioneer portable gaming speaker for Nintendo Switch 2',
    summary: 'Works with Nintendo Switch 2 and phones.', expect: true },

  // ── event coverage the model mislabels as a platform-less "title" ──────
  { name: 'gamescom, model said title', kind: 'title', claimed: [], src: RW,
    title: "'사일런트 힐: 타운폴' 영어 성우진 게임스컴 공개",
    summary: '게임스컴에서 영어 성우진이 공개됐다.', expect: true },
  { name: 'TGS, model said title', kind: 'title', claimed: [], src: FG,
    title: '東京ゲームショウ2026の出展情報が公開', summary: 'ブース情報が公開された。', expect: true },
  // Dedicated games outlet, model did not mark it excluded -> trusted even
  // with no platform named. This is why a Street Fighter 6 announcement from
  // 4Gamer is no longer dropped.
  { name: 'dedicated src, no platform', kind: 'title', claimed: [], src: FG,
    title: 'A new RPG is in development', summary: 'No further details.', expect: true },

  // ── specific titles still need a covered platform ───────────────────────
  { name: 'PS5 launch', kind: 'title', claimed: ['PlayStation 5'], src: RW,
    title: 'Gear Club Unlimited 3', summary: 'PS5 한국어판 10월 28일 출시.', expect: true },
  { name: 'Steam release', kind: 'title', claimed: ['PC'], src: FG,
    title: 'Indie puzzle hits Steam', summary: '8月27日にSteamでリリース。', expect: true },
  { name: 'SF6 character reveal', kind: 'title', claimed: [], src: FG,
    title: '「ストリートファイター6」Year 4第2弾「アルジュン」が10月13日に参戦',
    summary: 'リカバリアブルダメージを蓄積させて追い詰める。', expect: true },
  // Mixed-content source stays strict: there, a missing platform is the
  // anime-leak signal rather than a terse blurb.
  { name: 'mixed src, no platform', kind: 'title', claimed: [], src: GW,
    title: '新シリーズの制作が決定', summary: '詳細は後日発表。', expect: false },

  // ── anime and merchandise are NOT gaming, even from a games outlet ──────
  { name: 'anime episode (GW)', kind: 'not-gaming', claimed: [], src: GW,
    title: 'アニメ「攻殻機動隊」第8話の先行カットとあらすじが公開',
    summary: 'アニメ第8話の先行カット。', expect: false },
  { name: 'figure review', kind: 'not-gaming', claimed: [], src: GW,
    title: 'Pink Charm Eve BODY HARNESS Renewal Ver. figure review',
    summary: 'A scale figure review.', expect: false },
  { name: 'food product', kind: 'not-gaming', claimed: [], src: GW,
    title: '永谷園「マグカップでふっくらケーキ」一時休売', summary: '販売は継続。', expect: false },
  { name: 'game-franchise song', kind: 'not-gaming', claimed: ['PC'], src: FG,
    title: 'Robin Honkai: Star Rail releases new song Summer Sky Song',
    summary: 'A new character song was released.', expect: false },
  { name: 'capsule toy of a game', kind: 'not-gaming', claimed: ['Nintendo Switch'], src: GW,
    title: '【ドラクエ】カプセルトイ「モンスターフィギュア」再販', summary: '全5種。', expect: false },

  // ── out-of-scope platforms ──────────────────────────────────────────────
  { name: 'mobile gacha', kind: 'excluded', claimed: [], src: FG,
    title: 'New gacha banner', summary: 'iOS and Android only.', expect: false },
  { name: 'arcade only', kind: 'excluded', claimed: [], src: FG,
    title: 'New arcade cabinet', summary: 'Japanese arcades this winter.', expect: false },
  { name: 'PS4-only port', kind: 'excluded', claimed: ['PlayStation 5'], src: FG,
    title: 'Retro port', summary: 'Coming to PS4 and PS Vita only.', expect: false },

  // Real miss: a mobile pre-registration from a dedicated games outlet, which
  // the "trust the source" exception used to wave through as Video Game.
  { name: 'mobile pre-reg (4gamer)', kind: 'title', claimed: [], src: FG,
    title: '「シャングリラ・フロンティア」事前登録をApp StoreやGoogle Playで開始',
    summary: 'スマートフォン向けタイトルの事前登録が始まった。', expect: false },
  { name: 'dedicated src, PC stated, still ok', kind: 'title', claimed: [], src: FG,
    title: 'RPG update detailed', summary: 'Steam版のアップデートが配信。', expect: true },

  // ── source allowlist ────────────────────────────────────────────────────
  { name: 'retired: denfami', kind: 'title', claimed: ['PC'], src: 'news.denfaminicogamer.jp',
    title: 'Steam update announced', summary: 'Steam版のアップデート。', expect: false },
  { name: 'retired: automaton', kind: 'title', claimed: ['PC'], src: 'automaton-media.com',
    title: 'Roguelike 1.0', summary: 'Out now on Steam.', expect: false },
  // Games press feeding the Esports/Streamer pillars is approved for Video
  // Game too; its non-game output is routed by the pillar definitions.
  { name: 'approved: dotesports', kind: 'title', claimed: ['PC'], src: 'dotesports.com',
    title: 'Onimusha hits 1 million copies', summary: 'Strong PC launch.', expect: true },
  { name: 'approved: hltv', kind: 'ecosystem', claimed: [], src: 'www.hltv.org',
    title: 'Major roster shuffle ahead of the playoffs', summary: 'CS2 teams reshuffle.', expect: true },

  // ── hallucination guard on the mixed-content source ─────────────────────
  { name: 'GW anime claiming PC', kind: 'title', claimed: ['PC'], src: GW,
    title: 'アニメ第3期の制作決定', summary: 'アニメの続編が発表された。', expect: false },

  // ── missing gaming_kind falls back to the STRICT path ───────────────────
  { name: 'no kind + platform', kind: '', claimed: ['PC'], src: FG,
    title: 'Game hits Steam', summary: 'Now on Steam for PC.', expect: true },
  { name: 'no kind, no platform (mixed src)', kind: '', claimed: [], src: GW,
    title: 'Some announcement', summary: 'No platform stated.', expect: false },
];

let pass = 0, fail = 0;
for (const c of cases) {
  const v = checkGamingGate(c.claimed, c.title, c.summary, c.src, c.kind || undefined);
  const ok = v.ok === c.expect;
  ok ? pass++ : fail++;
  const detail = v.ok
    ? `ALLOW  kind=${v.kind} [${v.platforms.join('/') || 'no platform'}]`
    : `REJECT ${v.reason.slice(0, 58)}`;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${c.name.padEnd(24)} -> ${detail}`);
}

console.log(`\nnormalize("PS5, Steam") =`, normalizePlatforms('PS5, Steam'));
console.log(`textScan("Xbox Series X") =`, platformsInText('Update for Xbox Series X'));
console.log('sources:', ['game.watch.impress.co.jp','bbs.ruliweb.com','www.4gamer.net','automaton-media.com']
  .map((d) => `${d}=${isAllowedGamingSource(d)}`).join('  '));
console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail === 0 ? 0 : 1);
