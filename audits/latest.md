# Independent Audit - 2026-05-23T13:08:24Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
`lanes/*/lane-status.json`, recent Git history, dirty tree state, active
process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `d52cc007fe22` (`Refresh independent audit
status`). Recent history reviewed includes `d52cc007`, `24260634`,
`51867989`, `b75226d1`, `30be5e3c`, `90d1fa3b`, `81419ac3`,
`69405063`, `0f1444c1`, `efa4e0c2`, `f8bd46e4`, and `09995598`.

## Findings

1. **Critical - the checkout is not a stable integration checkpoint.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json`, `porting.html`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require capped workers,
     current owner/session tracking, small reviewable committed slices,
     integration handoff, and honest repo-wide verification.
   - Evidence: `progress.md:25` still says the launch target is 2
     implementation lanes plus 1 auditor, and `progress.md:31`-`42` still
     reports every lane session as `stopped`.
   - Evidence: process sampling found 23 active worker/status/test processes
     matching lane agents, auditor, integrator, dashboard-updater,
     evaluator, capacity-controller, Dolt runner, and SQLite TCL runner
     loops. Active lane watchdogs were observed for Syncthing, Readability,
     Gitoxide, rclone, Quadrable, markerPDF, Dolt, Pandoc, esbuild,
     LightningCSS, libsqlite, and Difftastic.
   - Evidence: the dirty tree is broad and still moving. Latest pre-edit
     samples reported `1581` default `git status --short --untracked-files=all`
     rows, `168` tracked changed files, and `168 files changed, 53178
     insertions(+), 5168 deletions(-)`. During this audit, status values
     moved: rclone lane PHP coverage changed from `463` to `468`, Gitoxide
     manifest mapped count moved from `1924` to `1939`, and markerPDF
     manifest denominator/mapped moved from `275/221` to `276/222`.
   - Audit judgment: current progress percentages, lane-local green claims,
     latest-commit fields, and root-test anecdotes are not acceptance evidence
     until active writers/status publishers are frozen and one regenerated
     snapshot is validated.

2. **High - the published dashboard is stale and still fails the dashboard
   contract.**
   - Paths: `porting.html:32`-`36`, `porting.html:40`-`65`,
     `porting-summary.json:2`-`8`, `porting-summary.json:10`-`213`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit in
     the generated dashboard.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8`
     still publish generated time `2026-05-23 04:57:16 UTC` and source
     commit `bda83c6b93d4`, while sampled `HEAD` is `d52cc007fe22`.
   - Evidence: `porting.html:40`-`50` still collapses required fields into
     compact `Benchmark` and `Mapped` columns instead of separate benchmark
     source, upstream denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree with current manifests/status files:

     | Lane | Dashboard denominator/mapped/php | Current manifest/status evidence |
     | --- | --- | --- |
     | difftastic | `417` / `160` / `160 pass` | manifest `587` artifacts / `268`; status php `268` |
     | dolt | `613` / `242` / `193 pass` | manifest `613` / `494`; status php `295` |
     | esbuild | `2567` / `164` / `164 pass` | manifest `2567` / `229`; status php `229` |
     | gitoxide | `2877` / `1432` / `2646 pass` | manifest `2877` / `1939`; status php `3506`, audit prose says `3542` assertions |
     | libsqlite | `1454` / `149` / `149 pass` | manifest `1589` / `216`; status php `216` |
     | LightningCSS | `3532` / `773` / `906 pass` | manifest `3532` / `1204`; status php `1337` |
     | markerPDF | `78` / `159` / `264 pass` | manifest `276` / `222`; status php `332` |
     | pandoc | `2028` / `426` / `164 pass` | manifest `2276` artifacts / `653`; status php `214` |
     | quadrable | `55` / `55` / `108 pass` | manifest `55` / `55`; status php `140` |
     | rclone | `327` / `291` / `291 pass` | manifest `2553` / `463`; status php `468` |
     | readability | `1984` / `1031` / `107 pass` | manifest `1984` / `1609`; status php `149` |
     | syncthing | `658` / `235` / `235 pass` | manifest `658` / `342`; status php `342` |

3. **High - manifest/status schemas still cannot produce trustworthy
   portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`,
     and `goal.md:45` require real upstream denominators, mapped upstream
     tests, PHP passing/failing counts, precise blockers, and generated
     dashboard fields backed by those values.
   - Evidence: denominator units remain mixed. Some manifests store numeric
     totals (`gitoxide`, `libsqlite`, `LightningCSS`, `markerPDF`, `rclone`,
     `readability`, `syncthing`), while others store prose-string totals
     (`difftastic`, `dolt`, `esbuild`, `pandoc`, `quadrable`).
   - Evidence: `runnerStatus` remains non-normalized. Some manifests use
     objects, some use strings, and some mix upstream runner results, static
     inventory rationale, PHP counts, skipped-suite rationale, and root-test
     anecdotes in the same field.
   - Evidence: manifest mapped counts and lane-status PHP counts are not the
     same unit. Examples: rclone manifest mapped `463` while
     `lanes/rclone/lane-status.json:6` reports `phpPass` `468`;
     Readability manifest mapped `1609` while status `phpPass` is `149`;
     Gitoxide manifest mapped `1939` while status `phpPass` is `3506`, and
     the audit prose in the same status file says the lane passed `3542`
     assertions.

4. **High - repo-wide PHP test records remain mutually non-comparable.**
   - Paths: `tools/run-tests.php`, `progress.md`,
     `lanes/*/lane-status.json:10`-`13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, `goal.md:48`,
     and `goal.md:49` require committed slices with passing tests, meaningful
     coverage beyond passing counts, verified handoff, and honest repo-wide
     test/static-check records.
   - Evidence: lane statuses currently mix focused-only handoffs, pending
     aggregate claims, duplicate-gated handoffs, green aggregate claims, and a
     red-then-green moving aggregate claim. Examples: `lanes/esbuild/lane-status.json:10`
     records one root run with 3 failures followed by a green rerun;
     `lanes/dolt/lane-status.json:10` records root green with `226` files /
     `26720` assertions; `lanes/pandoc/lane-status.json:10` records root green
     with `226` files / `26723` assertions; `lanes/rclone/lane-status.json:10`
     and `lanes/readability/lane-status.json:10` record root pending because
     other PHP harnesses were active.
   - Evidence: early exact duplicate-root gate samples returned no rows, but a
     final validation gate found active root PID `279690` plus focused PHP
     harnesses. No duplicate root run was started. Even before the active-root
     sample, the tree was not stable enough for a trustworthy root run because
     active writers/status publishers and broad upstream runners were still
     present.
   - Audit judgment: the next accepted test result must be one quiesced,
     no-argument `php tools/run-tests.php` run from one accepted tree, not
     lane-by-lane anecdotes or repeated roots gathered while workers mutate
     the checkout.

5. **Medium - high progress estimates are attached to unaccepted dirty
   batches rather than committed native slices.**
   - Paths: `lanes/*/lane-status.json:4`, `lanes/*/lane-status.json:13`,
     `progress.md:31`-`42`, recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:30`, `goal.md:36`,
     and `goal.md:48` require small committed native implementation slices,
     no bridge/generated progress credit, and supervisor acceptance before
     assigning next work.
   - Evidence: current lane estimates are high (`72%` to `99%` in
     `lanes/*/lane-status.json:4`), while every sampled lane `latestCommit`
     field is `pending`, `uncommitted`, or dirty-batch prose rather than an
     accepted commit ID.
   - Evidence: recent history is mostly audit-only commits around a few lane
     commits, while the worktree still contains `168` tracked changes and
     extensive untracked artifacts. That means the dashboard/progress claims
     are not tied to a reviewable accepted commit boundary.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed during the audit:

```text
279690 php tools/run-tests.php
286031 php tools/run-tests.php lanes/esbuild/tests lanes/rclone/tests lanes/syncthing/tests
286753 php tools/run-tests.php lanes/difftastic/tests lanes/lightningcss/tests lanes/quadrable/tests
287842 php tools/run-tests.php lanes/markerpdf/tests lanes/pandoc/tests lanes/readability/tests
287915 php tools/run-tests.php lanes/markerpdf/tests
```

Owner evidence captured before process exit:

```text
PID USER   PPID ELAPSED STAT COMMAND
279690 claude 279338 28 R php tools/run-tests.php
286031 claude 285808 12 R php tools/run-tests.php lanes/esbuild/tests lanes/rclone/tests lanes/syncthing/tests
287842 claude 287583 7 R php tools/run-tests.php lanes/markerpdf/tests lanes/pandoc/tests lanes/readability/tests
```

PIDs `286753` and `287915` exited before owner sampling. No duplicate root run
was started. Earlier exact gate samples were clear, but the stability condition
still failed: active lane agents, auditor/integrator/status loops, capacity
jobs, and broad Dolt/SQLite upstream runners were present, and manifest/status
values changed during the audit.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
pgrep -af 'run-tmux-agent|capacity|dashboard|evaluator|integrator|auditor|artifact|verifier|run-tests\.php'
rg -n '\b(proc_open|shell_exec|passthru|system|popen)\s*\(|\bexec\s*\(' lanes/*/src lanes/*/tests lanes/*/examples
git log --stat --oneline --decorate -n 12
git show --stat --oneline --decorate --no-renames HEAD
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON when checked. The shell-out scan
found only `PDO::exec()` calls in `lanes/syncthing/src/SqliteCheckpointStore.php`,
not bridge calls to external binaries. Latest pre-edit samples reported `1581`
default status rows, `168` tracked changed files, and `168 files changed,
53178 insertions(+), 5168 deletions(-)`.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize manifest/status denominator,
mapped, PHP pass/fail, runner, progress, and commit fields, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
that same accepted snapshot, rerun the exact duplicate-root gate, and capture
one quiesced no-argument `php tools/run-tests.php` result.
