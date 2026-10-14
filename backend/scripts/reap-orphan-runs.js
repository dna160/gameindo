/**
 * Repairs PipelineRun bookkeeping.
 *
 * Two problems this cleans up:
 *
 *   1. Orphaned RUNNING rows. isRunning is an in-process flag, so killing the
 *      server mid-run leaves the DB row saying RUNNING forever.
 *
 *   2. Aborted rows with a bogus completedAt. The dashboard picks its "last
 *      run" with `orderBy: { completedAt: 'desc' }`, so stamping a reaped row
 *      with the current time promotes a days-old run to the top and the
 *      dashboard appears frozen at that run's final log line. completedAt must
 *      reflect when the run actually stopped, which is its last log entry.
 *
 * Timestamps are written as real Date objects so Prisma stores UTC. Do NOT use
 * SQL now() against these columns: they are `timestamp without time zone`
 * holding UTC, and now() resolves to local time, landing rows in the future.
 *
 * Usage: node scripts/reap-orphan-runs.js [--stale-hours N] [--dry-run]
 */
const path = require('path');
require('dotenv').config({ path: path.resolve(__dirname, '../../.env') });
const { PrismaClient } = require('@prisma/client');

const prisma = new PrismaClient();
const args = process.argv.slice(2);
const dryRun = args.includes('--dry-run');
const staleHours = Number((args.find((a) => a.startsWith('--stale-hours=')) || '').split('=')[1]) || 6;

function lastLogTime(run) {
  try {
    const logs = JSON.parse(run.logs || '[]');
    if (Array.isArray(logs) && logs.length) {
      const t = new Date(logs[logs.length - 1].timestamp);
      if (!isNaN(t)) return t;
    }
  } catch (_) {}
  return run.startedAt;
}

(async () => {
  const cutoff = new Date(Date.now() - staleHours * 3600 * 1000);
  let fixed = 0;

  // 1. Orphaned RUNNING rows
  const orphans = await prisma.pipelineRun.findMany({
    where: { status: 'RUNNING', startedAt: { lt: cutoff } },
  });
  for (const run of orphans) {
    const at = lastLogTime(run);
    console.log(`RUNNING -> ABORTED  ${run.id}  started=${run.startedAt.toISOString()}  completedAt=${at.toISOString()}`);
    if (!dryRun) {
      await prisma.pipelineRun.update({
        where: { id: run.id },
        data: { status: 'ABORTED', completedAt: at },
      });
    }
    fixed++;
  }

  // 2. Aborted rows whose completedAt is implausible (future, or far past the
  //    last thing the run actually did).
  const aborted = await prisma.pipelineRun.findMany({ where: { status: 'ABORTED' } });
  for (const run of aborted) {
    if (!run.completedAt) continue;
    const at = lastLogTime(run);
    const driftHours = (run.completedAt.getTime() - at.getTime()) / 3600000;
    if (driftHours > 1) {
      console.log(`REPAIR completedAt  ${run.id}  ${run.completedAt.toISOString()} -> ${at.toISOString()}  (was ${driftHours.toFixed(1)}h off)`);
      if (!dryRun) {
        await prisma.pipelineRun.update({ where: { id: run.id }, data: { completedAt: at } });
      }
      fixed++;
    }
  }

  console.log(dryRun ? `\n[dry run] ${fixed} row(s) would change.` : `\n${fixed} row(s) updated.`);
  await prisma.$disconnect();
})().catch(async (e) => {
  console.error(e);
  await prisma.$disconnect();
  process.exit(1);
});
