/**
 * Continuous Pipeline Worker
 *
 * Runs the pipeline inside a Worker Thread.
 * worker.terminate() instantly kills it — no waiting for LLM calls to finish.
 */

import cron from 'node-cron';
import { Worker } from 'worker_threads';
import { PrismaClient } from '@prisma/client';
import path from 'path';
import './env';

const prisma = new PrismaClient();
const CRON_SCHEDULE = process.env.PIPELINE_CRON_SCHEDULE || '0 */2 * * *';
// Hard ceiling on a single pipeline run. If the worker thread ever hangs past
// this, the watchdog force-terminates it and resets state, so a stuck run can
// never wedge every future cron tick again. Kept well under the cron cadence.
const MAX_RUN_MS = Number(process.env.PIPELINE_MAX_RUN_MS) || 2 * 60 * 60 * 1000;

let worker: Worker | null = null;
let isRunning = false;
let currentRunId: string | null = null;

// Poll DB every 2s to track the active runId once the worker has created it
function startRunIdPoll(): NodeJS.Timeout {
  return setInterval(async () => {
    if (currentRunId) return;
    try {
      const run = await prisma.pipelineRun.findFirst({
        where: { status: 'RUNNING' },
        orderBy: { startedAt: 'desc' },
        select: { id: true },
      });
      if (run) currentRunId = run.id;
    } catch (_) {}
  }, 2000);
}

export async function runPipeline(): Promise<void> {
  if (isRunning) {
    console.log('[ContinuousPipeline] Already running — skipping.');
    return;
  }

  isRunning = true;
  currentRunId = null;

  const isDev = __filename.endsWith('.ts');
  const runnerExt = isDev ? '.ts' : '.js';
  const runnerPath = path.join(__dirname, `pipeline-runner${runnerExt}`);
  const execArgv = isDev
    ? ['--require', path.join(__dirname, '../node_modules/tsx/dist/cjs/index.cjs')]
    : [];
  console.log('[ContinuousPipeline] Starting pipeline worker thread...');

  // Worker construction can fail outright -- on 2026-08-28 PostgreSQL went
  // away mid-run and every subsequent spawn threw EINVAL /
  // ERR_WORKER_INIT_FAILED. Unguarded, that left isRunning stuck true with no
  // watchdog armed (the watchdog is set below), so the pipeline was wedged
  // until the process restarted: five consecutive runs aborted with 0 articles.
  try {
    worker = new Worker(runnerPath, { execArgv });
  } catch (err) {
    console.error('[ContinuousPipeline] Worker failed to start:', (err as Error).message);
    worker = null;
    isRunning = false;          // <- the reset that was missing
    currentRunId = null;
    try {
      await prisma.pipelineRun.updateMany({
        where: { status: 'RUNNING' },
        data:  { status: 'FAILED', completedAt: new Date() },
      });
    } catch { /* DB is probably the reason we are here */ }
    return;
  }

  const pollTimer = startRunIdPoll();

  // Watchdog: if the worker never exits (hung open handle, stalled LLM call
  // that outlives all retries, etc.) force-terminate it so isRunning is
  // guaranteed to reset. terminate() triggers the 'exit' handler below, which
  // does the state cleanup.
  const watchdog = setTimeout(() => {
    console.error(
      `[ContinuousPipeline] Watchdog: run exceeded ${MAX_RUN_MS}ms — terminating stuck worker.`
    );
    worker?.terminate().catch(() => {});
  }, MAX_RUN_MS);

  worker.on('message', (msg) => {
    if (msg.success) {
      console.log(`[ContinuousPipeline] Worker done. runId=${msg.runId} articles=${msg.articlesProcessed}`);
    } else {
      console.error('[ContinuousPipeline] Worker error:', msg.error);
    }
  });

  worker.on('exit', (code) => {
    clearInterval(pollTimer);
    clearTimeout(watchdog);
    console.log(`[ContinuousPipeline] Worker thread exited with code ${code}`);
    isRunning = false;
    worker = null;
    currentRunId = null;
  });

  worker.on('error', (err) => {
    clearInterval(pollTimer);
    clearTimeout(watchdog);
    console.error('[ContinuousPipeline] Worker thread error:', err.message);
    isRunning = false;
    worker = null;
    currentRunId = null;
  });
}

/**
 * Abort: terminate the worker thread instantly, then update DB.
 */
export async function abortPipeline(): Promise<boolean> {
  if (!isRunning || !worker) return false;

  console.log('[ContinuousPipeline] Terminating pipeline worker thread...');

  // Capture references before nulling them
  const runId = currentRunId;
  const workerToKill = worker;

  // Flip flags immediately so UI sees Idle on next poll
  isRunning = false;
  worker = null;
  currentRunId = null;

  // Terminate the thread — instantly kills all pending LLM awaits inside it
  try {
    await workerToKill.terminate();
    console.log('[ContinuousPipeline] Worker thread terminated.');
  } catch (_) {}

  // Update DB
  if (runId) {
    try {
      await prisma.pipelineRun.update({
        where: { id: runId },
        data: { status: 'ABORTED', completedAt: new Date() },
      });
      console.log(`[ContinuousPipeline] Run ${runId} marked ABORTED.`);
    } catch (_) {}
  } else {
    try {
      await prisma.pipelineRun.updateMany({
        where: { status: 'RUNNING' },
        data: { status: 'ABORTED', completedAt: new Date() },
      });
    } catch (_) {}
  }

  return true;
}

export function isPipelineRunning(): boolean {
  return isRunning;
}

async function startWorker(): Promise<void> {
  console.log(`[ContinuousPipeline] Cron schedule: "${CRON_SCHEDULE}"`);
  await runPipeline();
  cron.schedule(CRON_SCHEDULE, async () => {
    console.log('[ContinuousPipeline] Cron trigger fired.');
    await runPipeline();
  });
  console.log('[ContinuousPipeline] Cron scheduled. Worker active.');
}

const isDirectRun =
  process.argv[1]?.endsWith('continuous-pipeline.ts') ||
  process.argv[1]?.endsWith('continuous-pipeline.js');

if (isDirectRun) {
  startWorker().catch((err) => {
    console.error('[ContinuousPipeline] Fatal:', err);
    process.exit(1);
  });
}

export { startWorker };
