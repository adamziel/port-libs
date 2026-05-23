# Independent Audit - 2026-05-23T06:13:48Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, selected
lane status files needed to check dashboard/status alignment, recent Git
history through `df56249e5dca`, dirty-tree status, and active test/process
state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless a lane marks
it as temporary fixture/oracle evidence.

## Findings

1. **Critical - the repository is still non-quiescent, so a root harness now would be misleading.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:252`-`260`, `.tmux-team/tmp/*`,
     `.tmux-team/prompts/*`, `.tmux-team/logs/*`,
     `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and `tools/run-tests.php`.
   - Requirement at risk: `goal.md:20`, `goal.md:44`,
     `goal.md:48`, and `goal.md:49` require a practical concurrency cap,
     accurate owner/session state, deliberate integration, cleanup, and honest
     repo-wide verification.
   - Evidence: `progress.md` declares a two-implementation-worker plus one
     auditor target and shows every lane as `stopped`, but process sampling
     found 25 matching active processes: watchdog, capacity controller,
     dashboard updater, evaluator, auditor, artifact-acceptance, capacity
     shards, and active lane agents for Dolt, Pandoc, Gitoxide, Difftastic,
     rclone, esbuild, libsqlite, LightningCSS, Readability, Quadrable,
     Syncthing, and markerPDF.
   - Evidence: the dirty sample is `737` `git status --short` entries, `93`
     tracked changed files, and `93 files changed, 19570 insertions(+), 577
     deletions(-)`.
   - Required duplicate-root gate: `pgrep -af '^php tools/run-tests\.php( |$)'`
     returned no exact root harness at this sample. I still did not run
     `php tools/run-tests.php` because the active writers and broad dirty
     aggregate make the tree unstable enough that another root result would be
     diagnostic noise, not an accepted baseline.
   - Audit judgment: freeze writers first, then run exactly one root harness
     from a single accepted snapshot.

2. **High - `porting.html` and `porting-summary.json` are stale and still do not meet the dashboard contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`50`,
     `porting.html:54`-`65`, and `porting-summary.json`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and
     `goal.md:52` require current dashboard fields for benchmark source,
     upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios,
     phase, audit, current work, blocker, and commit.
   - Evidence: the page still advertises generated time
     `2026-05-23 04:57:16 UTC` and snapshot `bda83c6b93d4`, while current
     `HEAD` is `df56249e5dca`. The table still exposes compound `Benchmark`
     and `Mapped` columns instead of separate benchmark source, denominator,
     mapped tests, and PHP pass/fail columns.
   - Evidence: current manifests disagree with the page: Difftastic is
     `184 / 556` versus dashboard `160 / 417`; Dolt `289 / 613` versus
     `242 / 613`; esbuild `171 / 2567` versus `164 / 2567`; Gitoxide
     `1480 / 2877` versus `1432 / 2877`; libsqlite `169 / 1454` versus
     `149 / 1454`; LightningCSS `836 / 3532` versus `773 / 3532`; markerPDF
     `168 / 78` versus `159 / 78`; Pandoc `503 / 2028` versus `426 / 2028`;
     rclone `329 / 327` versus `291 / 327`; Readability `1130 / 1984` versus
     `1031 / 1984`; Syncthing `256 / 658` versus `235 / 658`. Quadrable is the
     only mapped count still aligned at `55 / 55`.
   - Audit judgment: the dashboard is an old publish snapshot, not the current
     coordination surface.

3. **High - manifest percentages remain mathematically indefensible.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, with examples at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`-`18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`18`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`-`18`, and
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`-`16`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     explicit slices for huge suites, and dashboard separation of denominator,
     mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is sometimes numeric and sometimes
     prose. Units mix files, test functions, BATS cases, fixture pairs,
     repository paths, inspected behavior artifacts, benchmark PDF pairs, and
     supplied-boundary examples.
   - Evidence: markerPDF reports `mapped=168` against a denominator string
     beginning with `78 tracked upstream repository paths`; rclone reports
     `mapped=329` against `total=327`. Both make percentage/average progress
     claims unreliable until the schema separates denominator units from mapped
     native behavior checks.
   - Evidence: `runnerStatus` is object-shaped in several lanes, string-shaped
     in Gitoxide/markerPDF/Quadrable, and missing from Pandoc. A consumer cannot
     distinguish full-runner pass, bounded-runner evidence, static inventory,
     oracle fixture, or supplied-boundary evidence consistently.
   - Audit judgment: normalize manifest schema before publishing portfolio
     percentages or using average progress for prioritization.

4. **High - lane status files contradict each other about root verification.**
   - Paths: `lanes/readability/lane-status.json:5`-`13`,
     `lanes/quadrable/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`, and
     `progress.md:252`-`260`.
   - Requirement at risk: `goal.md:31`, `goal.md:44`, and
     `goal.md:49` require precise blockers, current status, and honest
     repo-wide failure recording.
   - Evidence: Readability and Syncthing claim a root pass with `185` files and
     `19815` assertions; Quadrable records a later root failure with `10`
     Difftastic failures; rclone says root verification was skipped because PID
     `861697` was active; the immediately preceding `progress.md` audit entry
     says the prior audit skipped because PID `959908` was active. The current
     exact duplicate-root gate at this audit sample returned no root process.
   - Audit judgment: root status must be recorded once at the repository level
     for an accepted snapshot, then referenced by lanes. Copying lane-local
     root prose creates stale blockers and false green signals.

5. **High - the working tree is still a broad aggregate, not small reviewable slices.**
   - Paths: representative dirty surfaces include
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`,
     many `lanes/*/src/*`, many `lanes/*/tests/*`, `porting.html`,
     `porting-summary.json`, `.tmux-team/prompts/*`, and audit artifacts.
   - Requirement at risk: `goal.md:29`, `goal.md:36`,
     `goal.md:48`, and `goal.md:49` require small correct slices, cleanup of
     unrelated changes, verification, and passing repo-wide tests before
     acceptance.
   - Evidence: current dirty sampling still shows `93` tracked changed files
     and hundreds of untracked/generated artifacts while active agents continue
     running. Recent history also shows repeated audit-only commits interleaved
     with lane commits, so a root pass from this moving aggregate would not
     prove any single lane slice is ready.
   - Audit judgment: accept or reject one lane batch at a time only after
     active writers are stopped and the root harness passes from that frozen
     state.

6. **Medium - bounded/static evidence is still easy to misread as full upstream parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`19`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:72`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13`-`16`.
   - Requirement at risk: `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require upstream tests as the source of
     truth, meaningful fixture parity, hard-feature blockers, and no credit for
     generated/oracle/bridge work as native progress.
   - Evidence: Gitoxide remains bounded Cargo-package evidence rather than
     full workspace pass parity; Difftastic is still cloned static inventory;
     markerPDF cannot execute the full ML/PDF benchmark stack; Pandoc has no
     full Haskell runner parity; rclone/Dolt rely on bounded runner subsets;
     Syncthing lacks full `go test ./...` parity.
   - Audit judgment: add explicit evidence-type fields and keep bridge,
     generated fixture, and shell-backed oracle work out of native progress
     percentages.

## Bridge / Shell-Out Check

Command:

```text
rg -n 'proc_open|shell_exec|passthru|system\(|popen\(|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result:

```text
tools/generate-dashboard.php:183:    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
```

No lane PHP shell-out was found. The only PHP shell-out match is dashboard
coordination tooling.

## Test Gate

Required duplicate-root check before any root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Result: no output, exit status `1`.

I did not run `php tools/run-tests.php` because the tree was not stable enough:
active writer/watchdog/dashboard/evaluator processes were still running and the
dirty aggregate was too broad for a trustworthy accepted baseline.

## Recent Git History

Recent commits reviewed:

```text
df56249e Refresh independent audit status
a8dd5bb3 Record LightningCSS lane status
db0c8fe3 Port Syncthing platform xattr metadata boundaries
4ffd2048 Advance LightningCSS minifier slices
5a0bd45e Refresh independent audit status
b84cdfac Stamp libsqlite large replacement status
cf5fff72 Advance libsqlite large replacement overflow planning
89b251e7 Refresh independent audit status
b75cdedf Refresh independent audit status
0e312c81 Record esbuild verification counts
d54461d5 Stamp libsqlite lane commit
228941de Refresh independent audit status
```

## Recommended Next Intervention

Freeze active writers and duplicate loops, then rerun the exact duplicate-root
gate. If it remains empty, capture one quiesced `php tools/run-tests.php` run
from a single accepted snapshot. After that, accept or reject dirty lane batches
one at a time, regenerate `progress.md`, `porting.html`, `porting-summary.json`,
and lane statuses from the same snapshot, and normalize manifest
denominator/runner-status fields before publishing percentages.
