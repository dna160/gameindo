/**
 * Loads the repo-root .env, then runs the given command with it merged into
 * the environment — needed because Prisma CLI only auto-loads a .env that
 * sits next to schema.prisma or in the current working directory, and this
 * project keeps a single .env at the repo root instead.
 *
 * Usage: node scripts/with-root-env.js <command> [...args]
 */
const path = require('path');
const { spawnSync } = require('child_process');

require('dotenv').config({ path: path.resolve(__dirname, '../../.env') });

const [, , cmd, ...args] = process.argv;
const result = spawnSync(cmd, args, { stdio: 'inherit', shell: true, env: process.env });
process.exit(result.status ?? 1);
