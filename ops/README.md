# GameIndo newsroom — deployment

Clone, configure, run:

```
git clone https://github.com/dna160/gameindo.git
cd gameindo
copy .env.example .env          # fill it in -- every key is read by the code
powershell -ExecutionPolicy Bypass -File opssetup.ps1
powershell -ExecutionPolicy Bypass -File opsinstall-task.ps1
```

`opssetup.ps1` installs dependencies, generates the Prisma client, pushes the
schema, builds the dashboard into `backendpublic` and compiles the backend.
Re-run it after a `git pull` to rebuild. `opsinstall-task.ps1` registers the
always-on scheduled task.

Paths are resolved from the scripts' own location (`$PSScriptRoot`), so the
checkout works wherever it lands. The portable Node/PostgreSQL-client folder
defaults to `%SystemDrive%Claude Homeuntime`; set `GAMEINDO_RUNTIME` to
point elsewhere, or just have Node 22 on `PATH`.

Not installed by setup: Node 22, PostgreSQL 16, and an OpenAI-compatible LLM
server (LM Studio) with the model in `LLM_MODEL` available.

# GameIndo newsroom — local always-on deployment

A second instance of the popshck synthetic-newsroom app, configured for
GameIndo. Source copied from `C:\Claude Home\popshckv3` (no git remote of its
own — treat popshckv3 as upstream and re-copy when it changes).

## Topology

One Node process on **port 3000** serves all three things:

| Path | What |
|---|---|
| `/` | Operations dashboard (React build, served from `backend/public`) |
| `/api/*` | REST API |
| — | `node-cron` pipeline worker, schedule `0 */8 * * *` |

The cron runs *inside* that process, so keeping the cron alive is exactly the
same problem as keeping the process alive. That is the supervisor's whole job.

## Pieces

| Path | Role |
|---|---|
| `C:\Claude Home\runtime\node` | Portable Node 22.23.2 (no installer, no admin) |
| `C:\Claude Home\runtime\pgsql\bin` | PostgreSQL **client** tools only (`psql`, `pg_isready`) |
| `.env` | Single config file; read by backend at runtime and baked into the frontend at build time |
| `ops\supervise.ps1` | Restart loop + health probe |
| `ops\install-task.ps1` | Registers the `GameIndo Newsroom` scheduled task |
| `ops\setup-db.ps1` | Creates the `gameindo` database and pushes the Prisma schema |
| `ops\logs\` | `supervisor.log` plus per-restart `server-<stamp>.{out,err}.log` (newest 40 kept) |

## Database

Uses the **pre-existing** `postgresql-x64-16` Windows service
(`C:\Program Files\PostgreSQL\16\data`, StartType=Automatic), which also holds
popshck's database. GameIndo is a second database in the same cluster.

Do **not** start a second cluster on 5432. That was tried and it fails badly:
whichever cluster loses the race for the port leaves its app connecting to the
wrong data directory, and the app then crash-loops on "database does not exist".

## Persistence

`install-task.ps1` registers a per-user scheduled task with:

- **two** triggers: at logon, plus a `-Once` trigger dated in the past that
  repeats every 5 minutes indefinitely
- `ExecutionTimeLimit = PT0S` (no timeout — the task is never killed for running long)
- `RestartCount = 999`, `RestartInterval = 1 minute`
- `MultipleInstances = IgnoreNew`

The second trigger is the supervisor's own watchdog, and it has to be separate:
repetition hung off the **logon** trigger does not arm until that trigger next
fires, so on an already-logged-on machine it watches nothing. Verified by
killing the supervisor — with logon-only repetition nothing came back for 9
minutes; with the `-Once` trigger it was resurrected in 3m23s.

`RestartCount` alone is not enough either: in the 2026-08-20 outage the
supervisor died and the task never restarted it.

`supervise.ps1` then loops forever, every 15s:

1. Process exited? -> restart, backoff 5s doubling to 60s, reset after 2min stable.
2. Process alive but `/api/pipeline/status` not answering for 4 checks (~60s)?
   -> kill and restart. This matters because Prisma's connection pool does not
   recover on its own when PostgreSQL restarts underneath it — the process stays
   up but every request fails, so only an external probe catches it.
3. Process over 3 GB working set? -> restart it. On 2026-08-20 the pipeline ate
   the box and **PostgreSQL** was what died of OOM — and that service needs
   admin to restart, so the supervisor could not recover it. Our own process is
   the one we can restart unattended, so it is the one that must die first.
   Free system RAM under 600 MB is logged as a warning.

**Limitation:** a per-user task requires this user to be logged on. It survives
crashes, sign-outs and reboots-with-login, but not an unattended reboot that
stops at the lock screen. Making it truly logon-independent needs an admin
install (a real Windows service via NSSM, or a task with "run whether user is
logged on or not", which stores the account password).

## Operating it

```powershell
# status
Get-ScheduledTask -TaskName 'GameIndo Newsroom' | Select-Object State
Get-Content 'C:\Claude Home\gameindo-newsroom\ops\logs\supervisor.log' -Tail 20

# stop / start
Stop-ScheduledTask  -TaskName 'GameIndo Newsroom'
Start-ScheduledTask -TaskName 'GameIndo Newsroom'
```

## Rebuilding after a code change

```powershell
$env:Path = "C:\Claude Home\runtime\node;$env:Path"
cd 'C:\Claude Home\gameindo-newsroom\frontend'; npm run build
Copy-Item .\dist\* '..\backend\public' -Recurse -Force
cd '..\backend'; npx tsc
Stop-ScheduledTask -TaskName 'GameIndo Newsroom'; Start-ScheduledTask -TaskName 'GameIndo Newsroom'
```

Note: `npm run build` in `backend` will fail on Windows — its script ends in a
Unix `cp -r`, which cmd.exe does not have. Run `npx tsc` instead and copy
`src/agents/social_media/assets` by hand if the social pipeline is ever enabled.

## Windows PowerShell 5.1 gotcha

These `.ps1` files are ASCII-only and saved UTF-8 **with BOM**. PowerShell 5.1
reads a BOM-less script as ANSI, so a stray em-dash in a comment or string
becomes mojibake and takes the whole file out with a parse error.

## Scout: Gaming pillar platform gate

`backend/src/agents/gaming-platform-gate.ts` restricts the Gaming pillar to
**Nintendo Switch, PlayStation 5, PC, and Xbox**, and is enforced by BOTH
`scout.ts` and `scout-underquota.ts` (the underquota "sniper" path has its own
duplicate prompt and would otherwise bypass it).

Three layers, because prompt wording alone did not hold the local model — it
was filing anime coverage under Gaming:

1. The prompt scopes Gaming to those platforms and explicitly sends anime
   adaptations *of* a game to Anime.
2. Triage must return a `platforms` array.
3. Code requires a covered platform AND corroboration of it in the source text.
   The corroboration step is what actually stops an anime item passing on a
   hallucinated `"PC"`.

Watch the aliases if you edit them: bare `playstation` matches "PlayStation 4"
and bare `switch` matches the English verb. Both were real false positives
caught by `npm run test:gate` — keep that suite green.

Set `SCOUT_REQUIRE_PLATFORM_EVIDENCE=false` to accept the model's claim without
corroboration (looser; more anime leakage).

### Approved Gaming sources

Gaming may only come from **4gamer.net**, **game.watch.impress.co.jp** and
**ruliweb.com** (`GAMING_SOURCE_DOMAINS`). Feed `tags` in `services/rss.ts` do NOT constrain
what the LLM labels an item -- they only steer which feeds the Underquota
Protocol fetches -- so the rule is enforced in the gate. Automaton, Denfami and
GameSpark stay in the broad scrape untagged: their items can still
become anime/manga/toys articles, but never Gaming.

Do not assume the approved feeds are pure games. GAME Watch's RDF feed carries
anime episodes, capsule-toy restocks and even instant-food news from Impress's
wider desk; the platform gate is what keeps those out of Gaming, not the feed
list.

Corroboration is applied **per source** (`MIXED_CONTENT_SOURCES`):

| Source | Evidence required | Why |
|---|---|---|
| game.watch.impress.co.jp | yes | mixed feed, can misclassify |
| ruliweb.com | no | dedicated game news |

Ruliweb is Korean, so the gate carries Korean platform aliases
(`스위치`, `플스5`, `엑박`, `스팀`, ...) and both Scout prompts ask for Korean
romanisation. Without those aliases a headline like `스위치로 발매 결정` was
rejected outright, and a real Call of Duty story was dropped because the Korean
copy never spelled out a platform.


### Both scouts must agree

`Scout` and `UnderquotaProtocol` each have their own triage prompt, and both
call `checkGamingGate` with **identical arguments** — same source domain, same
rules, same verdict. An earlier version made the underquota path stricter to
compensate for its prompt bias; that only moved the disagreement around instead
of fixing the labelling, so the gate is deliberately symmetric now.

The underquota prompt names the pillar that is short, which biased the model
into forcing items there and inventing platforms to justify it — a Gamescom
interview came back as `gaming[Switch/PS5/PC]`. Two fixes, both in the prompt
rather than in more heuristics:

- the underquota prompt states the deficit list is **not a target to hit**, and
  that inventing a platform gets the item rejected;
- `PLATFORMS_FIELD_PROMPT` requires the item to be about a **specific game**,
  and names the categories that are never Gaming: trade shows, company and
  industry news, interviews, awards, esports-org business, hardware sales and
  store-front news.

`npm run test:scouts` drives the real `triageItem` on both classes with live
feed items and reports agreement. It calls the private method directly on
purpose: a full `run()` marks URLs processed and would consume candidates the
next scheduled pipeline needs.

Expect roughly 11/12 agreement. The residual gap is the model itself giving
different answers to two differently-worded prompts on genuinely ambiguous
items (a publisher showcase announcement), not a policy difference. Unifying
the two triage prompts is the next step if tighter agreement is wanted.

## Workflow audit — 2026-08-26

Full sweep of the chain from RSS to published post, checking that the Gaming
pillar means "video games on Switch / PS5 / PC / Xbox".

| # | Stage | Finding | Status |
|---|---|---|---|
| 1 | Feeds | Gaming restricted to 4gamer.net, game.watch, ruliweb | fixed |
| 2 | Scout triage | Scope rejected expos/interviews/esports as "not gaming" | fixed |
| 3 | Underquota triage | Same prompt bias, same gate — now symmetric | fixed |
| 4 | Topic bank | Recalled topics bypassed the gate entirely | fixed |
| 5 | Orchestrator | 10 slots/pillar; Gaming is 1/5 of output | by design |
| 6 | Copywriter Hikari | Persona primed for "mobile gacha games" | fixed |
| 7 | Editor | Internal link + footer pointed at popshck.com | fixed |
| 8 | Publisher | **Category IDs 10-14 do not exist on gameindo.com** | fixed |

### The one that mattered

Every article published to gameindo.com asked for category 10-14 — popshck's
IDs. gameindo.com has `blog(1) home(2) esports(3) streamer(4) tech(5)
entertainment(6)`, so WordPress fell back to the default and **all 519 posts
landed in `blog`**. Nothing was ever categorised as Video Game; the pillar
routing had never taken effect on this site at all.

Mapping now (`services/wp-taxonomy.ts`):

| Pillar | Category |
|---|---|
| gaming | 2 home (Video Game) |
| gaming + esports content | 3 esports |
| anime / manga / infotainment / toys | 6 entertainment |

The esports split is resolved from the **finished article** rather than carried
down from the Scout. That keeps both publish paths — the pipeline and the
dashboard's manual publish button — on one rule, and needs no Prisma migration
to backfill a sub-pillar on 519 existing rows. Markers are tuned for Indonesian
(`turnamen`, `kejuaraan`) because that is the language the articles are written
in, not the language of the sources. `msi` is excluded on purpose: it collides
with the hardware brand.

### Gaming scope

`gaming_kind` is now part of the triage contract: `title` | `ecosystem` |
`excluded` | `not-gaming`.

- `title` — a specific game; still requires a covered platform
- `ecosystem` — expos (gamescom, TGS), studio news, dev interviews, awards,
  esports, gaming hardware. **No platform required** — this content is
  legitimately platform-spanning
- `excluded` — mobile/gacha/arcade/legacy-only
- `not-gaming` — anime, manga, figures, music, live-action, food

An unknown or missing `gaming_kind` falls back to `title`, the strict path,
never the permissive one.

`ECOSYSTEM_MARKERS` also promote a platform-less `title` to `ecosystem` when the
item names an industry event. Added after a Silent Hill / gamescom announcement
was rejected for "no covered platform" — exactly the coverage the pillar wants.

### Not fixed (deliberate)

- The 519 already-published posts keep their popshck.com footer and their
  `blog` category. Fix-forward only, as agreed.
- `AUTHOR_IDS` (7-11) happen to be valid gameindo.com users, but they are
  different people from the popshck bylines the map was written for — Hikari's
  articles publish as "Gani Fighter" by ID collision, not by intent.
