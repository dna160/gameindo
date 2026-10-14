/**
 * Exercises the REAL triage path on both Scout and UnderquotaProtocol with
 * live feed items. Calls the private triageItem directly so nothing is written
 * to the DB and no URLs are marked processed -- a full run() would consume
 * candidates the next scheduled pipeline needs.
 */
import '../env';
import { PrismaClient } from '@prisma/client';
import { Scout } from './scout';
import { UnderquotaProtocol } from './scout-underquota';
import { fetchFeed } from '../services/rss';
import type { Pillar } from '../shared/types';

const prisma = new PrismaClient();
const quiet = (_: string) => {};

const FEEDS = [
  'https://www.4gamer.net/rss/index.xml',
  'https://game.watch.impress.co.jp/data/rss/1.0/gmw/feed.rdf',
  'https://bbs.ruliweb.com/news/rss',
  'https://news.denfaminicogamer.jp/feed',   // retired source -> must never be Gaming
];

(async () => {
  const scout = new Scout(prisma, quiet);
  const uq = new UnderquotaProtocol(prisma, quiet);
  const items: any[] = [];
  for (const url of FEEDS) {
    try { items.push(...(await fetchFeed(url, 'home' as Pillar)).slice(0, 3)); }
    catch (e) { console.log(`feed failed: ${url}`); }
  }

  let agree = 0, differ = 0;
  for (const it of items) {
    const a = await (scout as any).triageItem(it.title, it.summary || '', it.sourceFeed);
    const b = await (uq as any).triageItem(it.title, it.summary || '', ['home'] as Pillar[], it.sourceFeed);

    const fmt = (r: any) =>
      r.status === 'APPROVED'
        ? `${r.pillar}${r.platforms?.length ? '[' + r.platforms.join('/') + ']' : ''}`
        : `${r.status}: ${String(r.reason).slice(0, 44)}`;

    const aG = a.status === 'APPROVED' && a.pillar === 'home';
    const bG = b.status === 'APPROVED' && b.pillar === 'home';
    if (aG === bG) agree++; else differ++;

    console.log(`\n[${it.sourceFeed}] ${String(it.title).slice(0, 54)}`);
    console.log(`  scout     : ${fmt(a)}`);
    console.log(`  underquota: ${fmt(b)}`);
    console.log(`  gaming?   : scout=${aG} underquota=${bG} ${aG === bG ? '' : '  <-- MISMATCH'}`);
  }
  console.log(`\nGaming verdict agreement: ${agree}/${agree + differ}`);
  await prisma.$disconnect();
})().catch(async (e) => { console.error(e); await prisma.$disconnect(); process.exit(1); });
