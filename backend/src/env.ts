import dotenv from 'dotenv';
import path from 'path';

// Single source of truth for env vars lives at the repo root (../../.env
// relative to this file), regardless of the process's current working
// directory. Every entrypoint imports this module first.
dotenv.config({ path: path.resolve(__dirname, '../../.env') });
