# Independent Audit - 2026-05-23T13:00:22Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
`lanes/*/lane-status.json`, recent Git history, dirty tree state, active
process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `242606346415` (`Refresh independent audit
status`). Recent history reviewed includes `24260634`, `51867989`,
`b75226d1`, `30be5e3c`, `90d1fa3b`, `81419ac3`, `69405063`,
`0f1444c1`, `efa4e0c2`, `f8bd46e4`, `09995598`, and `a04f2c8b`.

## Findings

1. **Critical - the checkout is still not a stable integration checkpoint.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json`, `porting.html`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md` requires capped supervised workers,
     current owner/session tracking, small reviewable committed slices with
     passing tests, honest blockers, and a current generated dashboard.
   - Evidence: `progress.md:25` still says the launch target is 2
     implementation lanes plus 1 auditor, while `progress.md:31`-`42` reports
     all 12 lane sessions as `stopped`.
   - Evidence: process sampling found active markerPDF, Pandoc, Dolt,
     Difftastic, LightningCSS, esbuild, Syncthing, libsqlite, Readability,
     Gitoxide, auditor, rclone, Quadrable, capacity, team-watchdog, evaluator,
     dashboard-updater, capacity-controller, and Dolt-runner processes. A later
     broad sample matched 25 active worker/test/status processes.
   - Evidence: the dirty tree is still broad and moving. Latest samples
     reported `1572` default `git status --short --untracked-files=all` rows,
     `167` tracked changed files, and `167 files changed, 52118 insertions(+),
     5130 deletions(-)`. During this audit, the shortstat moved from
     `51797` to `52118` insertions and manifest mapped counts changed
     mid-read, including Difftastic `266` to `268`, esbuild `228` to `229`,
     and LightningCSS `1201` to `1204`.
   - Audit judgment: current progress percentages, lane-local green claims,
     latest-commit fields, and root-test anecdotes are not acceptance evidence
     until active writers/status publishers are frozen and one regenerated
     snapshot is validated.

2. **High - `porting.html` and `porting-summary.json` are stale and still
   fail the dashboard contract.**
   - Paths: `porting.html:32`-`36`, `porting.html:40`-`50`,
     `porting-summary.json:2`-`8`, `porting-summary.json:10`-`213`.
   - Goal requirement at risk: `goal.md` requires the dashboard to show current
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html` and `porting-summary.json` still publish
     generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while sampled `HEAD` is `242606346415`.
   - Evidence: `porting.html:40`-`50` still collapses required fields into
     compact `Benchmark` and `Mapped` columns instead of separate benchmark
     source, upstream denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree with current manifests/status files:

     | Lane | Dashboard denominator/mapped/php | Current manifest/status evidence |
     | --- | --- | --- |
     | difftastic | `417` / `160` / `160 pass` | manifest `587` artifacts / `268`; status php `268` |
     | dolt | `613` / `242` / `193 pass` | manifest `613` / `494`; status php `295` |
     | esbuild | `2567` / `164` / `164 pass` | manifest `2567` / `229`; status php `228` |
     | gitoxide | `2877` / `1432` / `2646 pass` | manifest `2877` / `1924`; status php `3506` assertions |
     | libsqlite | `1454` / `149` / `149 pass` | manifest `1589` / `215`; status php `215` |
     | LightningCSS | `3532` / `773` / `906 pass` | manifest `3532` / `1204`; status php `1333` assertions |
     | markerPDF | `78` / `159` / `264 pass` | manifest `275` / `221`; status php `332` |
     | pandoc | `2028` / `426` / `164 pass` | manifest `2276` artifacts / `653`; status php `214` |
     | quadrable | `55` / `55` / `108 pass` | manifest `55` / `55`; status php `139` |
     | rclone | `327` / `291` / `291 pass` | manifest `2553` / `463`; status php `463` |
     | readability | `1984` / `1031` / `107 pass` | manifest `1984` / `1594`; status php `148` |
     | syncthing | `658` / `235` / `235 pass` | manifest `658` / `339`; status php `339` |

3. **High - repo-wide PHP test records remain mutually non-comparable.**
   - Paths: `tools/run-tests.php`, `progress.md`,
     `lanes/*/lane-status.json:10`-`13`.
   - Goal requirement at risk: `goal.md` requires repo-wide tests/static
     checks to be recorded honestly, and passing tests must be tied to accepted
     native slices rather than moving dirty aggregates.
   - Evidence: lane statuses currently mix focused-only handoffs, pending root
     claims, duplicate-gated handoffs, green aggregate claims with different
     assertion totals, and one red aggregate claim. Examples:
     `lanes/libsqlite/lane-status.json:10`-`13` records a root failure outside
     libsqlite with 5 rclone failures; `lanes/esbuild/lane-status.json:10`-`13`
     records root green with 226 files / 26559 assertions;
     `lanes/dolt/lane-status.json:10`-`13` records root green with 226 files /
     26720 assertions; `lanes/pandoc/lane-status.json:10`-`13` records root
     green with 226 files / 26723 assertions; `lanes/rclone/lane-status.json:10`-`13`
     records root pending because another gate match was active.
   - Evidence: the required gate later matched focused harnesses
     `226223 php tools/run-tests.php lanes/syncthing/tests` and
     `226348 php tools/run-tests.php lanes/readability/tests`. Owner evidence
     for `226223` showed user `claude`; `226348` exited before owner sampling.
   - Audit judgment: the next accepted test result must be one quiesced
     no-argument `php tools/run-tests.php` run from one accepted tree, not
     lane-by-lane anecdotes gathered while workers mutate the checkout.

4. **High - manifest/status schemas still cannot produce trustworthy
   portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
   - Goal requirement at risk: `goal.md` requires real upstream denominators,
     mapped upstream tests, PHP passing/failing counts, precise blockers, and a
     generated dashboard backed by those fields.
   - Evidence: denominator units remain mixed. Some manifests use numeric
     totals (`gitoxide`, `libsqlite`, `LightningCSS`, `markerPDF`, `rclone`,
     `readability`, `syncthing`), while others store prose-string totals
     (`difftastic`, `dolt`, `esbuild`, `pandoc`, `quadrable`).
   - Evidence: `runnerStatus` remains non-normalized. Some files use objects,
     some use strings, and Pandoc lacks a comparable denominator-level runner
     status field. Contents mix upstream runner pass/fail, static reads,
     focused package runners, PHP counts, root-test anecdotes, and skipped
     suite rationale.
   - Evidence: manifest mapped counts and lane-status PHP counts are not the
     same unit. For example, esbuild currently has manifest mapped `229` but
     status php `228`, Gitoxide has manifest mapped `1924` but status php
     `3506` assertions, and LightningCSS has manifest mapped `1204` but status
     php `1333` assertions.

5. **Medium - too much evidence is still unaccepted lane-local work rather
   than committed native implementation progress.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires small, reviewable commits and
     says generated fixtures, bridge calls, and shell-outs must not count as
     native implementation progress.
   - Evidence: most lane `latestCommit` fields still say `pending`,
     `uncommitted`, `not committed`, or dirty-batch prose. These fields are
     being paired with high estimates (`88%`-`99%` in several lanes) and green
     focused tests even though the supervisor has not accepted or rejected the
     dirty lane batches one at a time.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed during the audit:

```text
226223 php tools/run-tests.php lanes/syncthing/tests
226348 php tools/run-tests.php lanes/readability/tests
232039 php tools/run-tests.php lanes/quadrable/tests
```

Owner evidence captured before process exit:

```text
PID USER   PPID ELAPSED STAT COMMAND
226223 claude 214632 14 Rs php tools/run-tests.php lanes/syncthing/tests
232039 claude 224686 6 Rs php tools/run-tests.php lanes/quadrable/tests
```

PID `226348` exited before owner sampling. Intervening exact gates alternated
between clear and focused-test matches; no root run was started because the
tree was still not stable enough and focused test/status processes persisted.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -o pid,user,ppid,etimes,stat,args -p 226223,226348
ps -o pid,user,ppid,etimes,stat,args -p 232039
rg -n '\b(proc_open|shell_exec|passthru|system|popen)\s*\(|\bexec\s*\(' lanes/*/src lanes/*/tests lanes/*/examples
git log --oneline --decorate -n 12
git show --stat --oneline --decorate --no-renames HEAD
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON when checked. The shell-out scan
found only `PDO::exec()` calls in `lanes/syncthing/src/SqliteCheckpointStore.php`,
not a bridge to external binaries. Latest samples reported `1572` default
status rows, `167` tracked changed files, and `167 files changed, 52118
insertions(+), 5130 deletions(-)`.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize manifest/status denominator, mapped,
PHP pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced no-argument `php tools/run-tests.php` result.
