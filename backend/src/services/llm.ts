import '../env';

if (!process.env.LLM_API_KEY) {
  throw new Error('LLM_API_KEY environment variable is required');
}

// Talks to any OpenAI-compatible chat/completions endpoint — defaults to a
// locally running LM Studio server. LM Studio doesn't check the API key
// value, but the header must still be present, so any non-empty string works.
const LLM_API_KEY  = process.env.LLM_API_KEY!;
const LLM_BASE_URL = process.env.LLM_BASE_URL ?? 'http://localhost:1234/v1';

export const MODEL = process.env.LLM_MODEL ?? 'local-model';

// ── Types ─────────────────────────────────────────────────────────────────────

export type ContentPart =
  | { type: 'text'; text: string }
  | { type: 'image_url'; image_url: { url: string } };

export interface ChatMessage {
  role: 'system' | 'user' | 'assistant';
  content: string | ContentPart[];
}

interface LlmChoice {
  message: {
    content: string | null;
  };
  finish_reason: 'stop' | 'length' | 'content_filter' | string | null;
}

interface LlmResponse {
  choices: LlmChoice[];
}

// ── Rate limiter ──────────────────────────────────────────────────────────────
// A local model serves requests one at a time anyway, so this just needs to
// avoid piling up unbounded parallel requests against the local server.

class RateLimiter {
  private queue: Array<() => void> = [];
  private timestamps: number[] = [];
  private readonly maxPerMinute: number;
  private timer: ReturnType<typeof setTimeout> | null = null;

  constructor(maxPerMinute: number) {
    this.maxPerMinute = maxPerMinute;
  }

  acquire(): Promise<void> {
    return new Promise((resolve) => {
      this.queue.push(resolve);
      this.process();
    });
  }

  private process(): void {
    if (this.queue.length === 0) return;

    const now = Date.now();
    const windowStart = now - 60_000;
    this.timestamps = this.timestamps.filter((t) => t > windowStart);

    if (this.timestamps.length < this.maxPerMinute) {
      const resolve = this.queue.shift()!;
      this.timestamps.push(Date.now());
      resolve();
      if (this.queue.length > 0) {
        setImmediate(() => this.process());
      }
    } else {
      const waitMs = this.timestamps[0] - windowStart + 1;
      if (!this.timer) {
        this.timer = setTimeout(() => {
          this.timer = null;
          this.process();
        }, waitMs);
      }
    }
  }
}

const rateLimiter = new RateLimiter(Number(process.env.LLM_MAX_PER_MINUTE) || 2500);

// ── Timeout helper ────────────────────────────────────────────────────────────
// Local inference is much slower than a cloud API, so the default is generous.

const CHAT_TIMEOUT_MS = Number(process.env.LLM_TIMEOUT_MS) || 300_000; // 5 minutes

function withTimeout<T>(promise: Promise<T>, ms: number, label: string): Promise<T> {
  let timer: ReturnType<typeof setTimeout>;
  const timeout = new Promise<never>((_, reject) => {
    timer = setTimeout(
      () => reject(new Error(`LLM request timed out after ${ms}ms (${label})`)),
      ms
    );
  });
  return Promise.race([promise, timeout]).finally(() => clearTimeout(timer));
}

// ── Core HTTP client ──────────────────────────────────────────────────────────

/**
 * Low-level HTTP call to the configured chat completions endpoint.
 * Use chat() for all standard calls — it adds rate limiting, retry, and
 * automatic token-doubling on truncation.
 */
export async function createCompletion(params: {
  model: string;
  messages: ChatMessage[];
  temperature?: number;
  max_tokens?: number;
}): Promise<LlmResponse> {
  const res = await fetch(`${LLM_BASE_URL}/chat/completions`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${LLM_API_KEY}`,
    },
    body: JSON.stringify({
      model:       params.model,
      messages:    params.messages,
      temperature: params.temperature ?? 0.7,
      max_tokens:  params.max_tokens  ?? 4096,
    }),
  });

  if (!res.ok) {
    const body = await res.text();
    throw new Error(`LLM API error ${res.status}: ${body}`);
  }

  return res.json() as Promise<LlmResponse>;
}

// ── High-level chat helper ────────────────────────────────────────────────────

const MAX_TOKENS_CAP   = Number(process.env.LLM_MAX_TOKENS_CAP) || 32000;

/**
 * Floor for the FIRST attempt's max_tokens.
 *
 * Call sites ask for 256-1024 tokens, but gemma routinely overruns those, and
 * every overrun costs a full wasted call: finish_reason=length, discard the
 * output, double the budget, call again. One 2026-09-01 run logged 501 such
 * retries and never finished a single article inside the 2h watchdog.
 *
 * max_tokens is a ceiling, not a target -- the model still stops at its
 * natural stop token -- so raising the floor costs nothing when the reply is
 * short, and removes the retry cycle when it is not.
 */
const MIN_INITIAL_TOKENS = Number(process.env.LLM_MIN_TOKENS) || 2048;
const MAX_ATTEMPTS     = 4;    // up to 3 token-doubling retries + 1 empty retry

/**
 * Send a chat completion request to the configured LLM and return the text content.
 *
 * Automatic retry strategy:
 *   - finish_reason=length (truncated): doubles max_tokens and retries (up to MAX_TOKENS_CAP)
 *   - empty content: retries up to 3 times with the same token budget
 *   - timeout / API error: propagates immediately (caller's try/catch handles it)
 */
export async function chat(
  messages: ChatMessage[],
  opts: { temperature?: number; maxTokens?: number } = {}
): Promise<string> {
  let maxTokens = Math.min(
    Math.max(opts.maxTokens ?? 32000, MIN_INITIAL_TOKENS),
    MAX_TOKENS_CAP,
  );

  for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
    await rateLimiter.acquire();

    let response: LlmResponse;
    try {
      response = await withTimeout(
        createCompletion({
          model:       MODEL,
          messages,
          temperature: opts.temperature ?? 0.7,
          max_tokens:  maxTokens,
        }),
        CHAT_TIMEOUT_MS,
        `chat attempt ${attempt}`
      );
    } catch (err) {
      // Timeout or network error — propagate immediately, don't retry
      throw err;
    }

    const choice       = response.choices[0];
    const content      = choice?.message?.content;
    const finishReason = choice?.finish_reason;

    // Truncated response — double the token budget and retry
    if (finishReason === 'length') {
      const next = Math.min(maxTokens * 2, MAX_TOKENS_CAP);
      console.warn(
        `[llm] Response truncated (finish_reason=length, tokens=${maxTokens}) — ` +
        `retrying with ${next} tokens (attempt ${attempt}/${MAX_ATTEMPTS})`
      );
      if (next === maxTokens) {
        // Already at cap — nothing more we can do
        throw new Error(
          `LLM response still truncated at max token cap (${MAX_TOKENS_CAP}). ` +
          `Consider shortening the prompt.`
        );
      }
      maxTokens = next;
      continue;
    }

    // Empty content — retry with the same settings
    if (!content) {
      if (attempt < MAX_ATTEMPTS) {
        console.warn(`[llm] Empty response from LLM (attempt ${attempt}/${MAX_ATTEMPTS}) — retrying…`);
        continue;
      }
      throw new Error(`LLM returned empty response after ${attempt} attempt(s)`);
    }

    return content.trim();
  }

  throw new Error('LLM chat: exhausted all retry attempts');
}

// ── Image relevance check (vision) ────────────────────────────────────────────

// Skip the vision call for anything unreasonably large — local vision models
// choke on huge payloads, and SERPER results are thumbnail/medium resolution
// anyway. Images over this size fail open (treated as relevant) rather than
// rejected, since size alone says nothing about topical relevance.
const MAX_VISION_IMAGE_BYTES = 8 * 1024 * 1024;

// Many image CDNs (Wikipedia included) block plain server-side fetches and
// return an HTML challenge/error page instead of the image. Browser-like
// headers avoid that in most cases; images.weserv.nl is the fallback proxy
// for the CDNs that still block it (mirrors researcher.ts's probe logic).
const IMAGE_FETCH_HEADERS: Record<string, string> = {
  'User-Agent':      'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
  'Accept':          'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
  'Accept-Language': 'en-US,en;q=0.9',
};

async function downloadImage(
  url: string
): Promise<{ buf: Buffer; contentType: string } | null> {
  const controller = new AbortController();
  const timeoutId   = setTimeout(() => controller.abort(), 8_000);
  try {
    const res = await fetch(url, { headers: IMAGE_FETCH_HEADERS, signal: controller.signal });
    clearTimeout(timeoutId);
    const contentType = (res.headers.get('content-type') ?? '').split(';')[0].trim();
    if (!res.ok || !contentType.startsWith('image/')) return null;

    const buf = Buffer.from(await res.arrayBuffer());
    return buf.length > 0 ? { buf, contentType } : null;
  } catch {
    clearTimeout(timeoutId);
    return null;
  }
}

/**
 * Check whether an image is both reachable and topically relevant to the
 * given article, using the configured LLM's vision support. Downloads the
 * image, sends it to the model alongside the topic/pillar, and asks for a
 * yes/no verdict.
 *
 * Fails open (returns true) on any download/vision error — a broken relevance
 * check shouldn't block an otherwise-fine pipeline run.
 */
export async function evaluateImageRelevance(
  imageUrl: string,
  topic:    string,
  pillar:   string
): Promise<boolean> {
  let downloaded = await downloadImage(imageUrl);

  if (!downloaded) {
    // Direct fetch blocked (bot-wall, CDN challenge page, etc.) — retry via
    // the same proxy researcher.ts's probe falls back to.
    const stripped = imageUrl.replace(/^https?:\/\//, '');
    const proxyUrl = `https://images.weserv.nl/?url=${encodeURIComponent(stripped)}&output=jpg&q=80`;
    downloaded = await downloadImage(proxyUrl);
  }

  if (!downloaded) return false;
  const { buf, contentType } = downloaded;

  if (buf.length > MAX_VISION_IMAGE_BYTES) return true;

  try {
    const dataUrl = `data:${contentType};base64,${buf.toString('base64')}`;
    const raw = await chat(
      [
        {
          role: 'user',
          content: [
            { type: 'image_url', image_url: { url: dataUrl } },
            {
              type: 'text',
              text:
                `Does this image plausibly illustrate a "${pillar}" news article titled "${topic}"? ` +
                `Answer with exactly one word: YES or NO.`,
            },
          ],
        },
      ],
      { temperature: 0, maxTokens: 600 }
    );
    return /\byes\b/i.test(raw) && !/\bno\b/i.test(raw);
  } catch (err) {
    console.warn(`[llm] Image relevance check failed, approving by default: ${(err as Error).message}`);
    return true;
  }
}

// ── JSON parsing helper ───────────────────────────────────────────────────────

/**
 * Parse a JSON response from the LLM, stripping markdown code fences if present.
 */
export function parseJsonResponse<T>(raw: string): T {
  const cleaned = raw
    .replace(/^```(?:json)?\s*/i, '')
    .replace(/\s*```$/i, '')
    .trim();
  return JSON.parse(cleaned) as T;
}
