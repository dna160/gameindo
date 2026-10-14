/**
 * Pipeline Runner — executed inside a Worker Thread by continuous-pipeline.ts
 *
 * When run as a worker, the parent can call worker.terminate() to instantly
 * stop execution regardless of what LLM call is in progress.
 */

import { workerData, isMainThread, parentPort } from 'worker_threads';
import { PrismaClient } from '@prisma/client';
import { Pipeline } from './pipeline';
import './env';

// Guard: only run pipeline logic when inside a worker thread
if (!isMainThread) {
  const prisma = new PrismaClient();

  (async () => {
    try {
      const pipeline = new Pipeline(prisma);
      const result = await pipeline.run();
      parentPort?.postMessage({ success: true, runId: result.runId, articlesProcessed: result.articlesProcessed });
    } catch (err) {
      parentPort?.postMessage({ success: false, error: (err as Error).message });
    } finally {
      try {
        await prisma.$disconnect();
      } catch (_) {}
      // Force the worker thread to terminate. Resolving the async IIFE is NOT
      // enough: any lingering open handle (HTTP keep-alive sockets, a timer, a
      // second Prisma pool, etc.) keeps the thread's event loop alive, so the
      // thread never exits, the parent's 'exit' handler never fires, and
      // isRunning is never reset — wedging every future cron tick into
      // "Already running — skipping." Exiting here guarantees the exit event.
      process.exit(0);
    }
  })();
}
