# Independent Audit - 2026-05-23T15:25:03Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, active process state, dirty
tree state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions,
push, read secrets, inspect process environments, credential stores, provider
configs, or auth files, or start a root harness. Bridge code, generated
fixtures, supplied fixtures, and shell-backed evidence are treated as
non-progress unless explicitly temporary oracle tooling.

Sampled `HEAD`: `2799d6e12da8` (`Refresh independent audit status`).
Recent Git history sampled by `git log --oneline --name-only -n 5` remains
audit-only `Refresh independent audit status` commits touching only
`audits/latest.md` and `progress.md`, while lane implementation, manifest, and
status files remain broadly dirty.

Manifest/status snapshot:

| Lane | Manifest denominator | Mapped | Lane PHP pass/fail | Commit/status field |
| --- | ---: | ---: | ---: | --- |
| difftastic | prose `598 inspected...` | 292 | 291 / 0 | pending in shared dirty worktree |
| dolt | prose `613 upstream...` | 558 | 305 / 0 | not committed |
| esbuild | prose `2,567 counted...` | 245 | 245 / 0 | uncommitted |
| gitoxide | 2877 | 2008 | 3829 / 0 | pending |
| libsqlite | 1589 | 239 | 239 / 0 | uncommitted |
| lightningcss | 3532 | 1315 | 1484 / 0 | uncommitted dirty worktree |
| markerPDF | 285 | 233 | 355 / 0 | uncommitted |
| pandoc | prose `2276 upstream...` | 716 | 227 / 0 | pending |
| quadrable | prose `55 tracked...` | 55 | 149 / 0 | pending |
| rclone | 1601 | 517 | 517 / 0 | pending |
| readability | 1984 | 1789 | 160 / 0 in status, 161 in manifest | uncommitted |
| syncthing | 658 | 381 | 381 / 0 | pending |

## Findings

1. **Critical - the repository is not stable enough for an accepted aggregate root-test baseline.**
   - Paths: `progress.md:25`, `progress.md:31`, `progress.md:42`,
     `.tmux-team/prompts/*`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires capped supervised lane work,
     current owner/session tracking, small committed slices with passing tests,
     periodic repo-wide tests, and honest failure recording.
   - Evidence: `progress.md:25` still documents a target of two
     implementation lanes plus one auditor; `progress.md:31` through
     `progress.md:42` reports every lane session as `stopped`.
   - Evidence: process sampling still found 27 matching watchdog, capacity,
     dashboard, evaluator, integrator, auditor, primary lane-agent, artifact,
     and root-test processes, including active agents for many lanes.
   - Evidence: the latest dirty-tree sample reported `1979` default
     `git status --short` rows, `196` tracked dirty rows, and
     `196 files changed, 76894 insertions(+), 7265 deletions(-)`.
   - Evidence: the required duplicate-root gate found active root PID
     `1979442` owned by `claude`, and a later validation gate found active
     root PID `1983399` owned by `claude`; this auditor did not start a
     duplicate.
   - Audit judgment: a no-argument `php tools/run-tests.php` result cannot be
     accepted as one integration checkpoint while writers, status publishers,
     and another root harness are active against a broad dirty tree.

2. **Critical - `porting.html` and `porting-summary.json` are stale and do not satisfy the dashboard contract.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:33`,
     `porting.html:54`, `porting.html:65`, `porting-summary.json:2`,
     `porting-summary.json:8`, `porting-summary.json:11`,
     `porting-summary.json:212`.
   - Goal requirement at risk: `goal.md` requires a generated dashboard with
     average progress and per-lane columns for library, suite progress,
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32` and `porting-summary.json:2` still publish
     `2026-05-23 04:57:16 UTC`; `porting.html:33` and
     `porting-summary.json:3` through `porting-summary.json:5` still point at
     `bda83c6b93d4`, while sampled `HEAD` is `2799d6e12da8`.
   - Evidence: published rows disagree with current manifests/status files.
     Difftastic is published as `160 / 417` mapped and `160 pass` at
     `porting.html:54`, while current files report manifest `292 / 598` and
     lane status `291 pass`. rclone is published as `291 / 327` and
     `291 pass` at `porting.html:63`, while current files report
     `517 / 1601` and `517 pass`. markerPDF is published as `159 / 78` and
     `264 pass` at `porting.html:60`, while current files report
     `233 / 285` and `355 pass`.
   - Evidence: the dashboard still collapses benchmark source, denominator,
     mapped tests, and PHP pass/fail into compact cells instead of the
     first-class goal columns.

3. **High - `progress.md` no longer describes the active system or current lane progress.**
   - Paths: `progress.md:25`, `progress.md:31`, `progress.md:42`,
     `lanes/*/lane-status.json:5`, `lanes/*/lane-status.json:6`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include
     active lanes, current owner/session, next task per lane, percentage
     estimates, latest commit, and blockers.
   - Evidence: `progress.md:31` through `progress.md:42` lists every lane as
     stopped with old estimates from `5%` to `66%`; current lane statuses
     describe fresh focused slices and much higher PHP pass counts.
   - Evidence: active process sampling contradicts the stopped-lane table:
     primary lane agents, auditor, integrator, capacity jobs, dashboard updater,
     and evaluator loops were present.
   - Evidence: progress history repeatedly records the same freeze-first
     intervention, but the active lane table and public dashboard remain stale.

4. **High - claimed lane progress is mostly unaccepted dirty-batch work, not small committed slices.**
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
     `lanes/syncthing/lane-status.json:13`, recent Git history.
   - Goal requirement at risk: `goal.md` requires small, reviewable committed
     slices with passing tests and latest commit tracking per lane.
   - Evidence: latest-commit fields include `pending`, `not committed`,
     `uncommitted`, `pending lane batch`, and dirty-worktree prose across the
     portfolio.
   - Evidence: the latest five Git commits sampled are all audit-only refreshes
     of `audits/latest.md` and `progress.md`, not integration commits for the
     currently claimed lane slices.

5. **High - manifest/status count schemas are still non-normalized.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/*/lane-status.json:5`, `lanes/*/lane-status.json:6`,
     `porting-summary.json:15`, `porting-summary.json:212`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator, mapped upstream tests, PHP passing/failing counts, and a
     dashboard whose rows can be compared across lanes.
   - Evidence: several `benchmarkDenominator.total` values are prose strings
     mixing files, tests, helper invocations, fixtures, and benchmark entries
     instead of one numeric denominator unit.
   - Evidence: mapped units and PHP pass units are not comparable:
     Gitoxide reports `2008` mapped but `3829` PHP passes; LightningCSS
     reports `1315` mapped and `1484` PHP passes; markerPDF reports `233`
     mapped and `355` PHP passes; Readability reports `1789` mapped upstream
     checks while status says `160` PHP passes and manifest says `161`.
   - Evidence: some dashboard values are mathematically stale or impossible,
     for example markerPDF `159 / 78` in `porting-summary.json:117` through
     `porting-summary.json:120`.

6. **High - near-complete percentages and "no blocker" language mask explicit upstream-runner and hard-feature gaps.**
   - Paths: `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:207`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:604`.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of
     truth, passing tests are not enough, and hard features must be marked as
     blockers or future slices rather than silently skipped.
   - Evidence: statuses say no local blocker while the same fields list major
     unexecuted or future work: full Gitoxide Cargo workspace, full SQLite
     all/release permutations, markerPDF live Streamlit/Python/model/benchmark
     paths, Pandoc Haskell runner, rclone live provider/mount parity, Syncthing
     full `go test ./...`, and esbuild `make test-all`.
   - Audit judgment: these may be valid future slices, but the current status
     surface overstates readiness if percentages and "no blocker" are read as
     near-native parity.

7. **Medium - direct PHP shell-out risk is low in lane code, but evidence accounting still blends generated/supplied artifacts with native parity.**
   - Paths: `lanes/syncthing/src/SqliteCheckpointStore.php:21`,
     `lanes/syncthing/src/SqliteCheckpointStore.php:186`,
     `lanes/syncthing/src/SqliteCheckpointStore.php:195`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`.
   - Goal requirement at risk: `goal.md` says generated fixtures, bridge
     calls, and shell-outs must not count as native implementation progress
     unless explicitly temporary oracle tooling.
   - Evidence: `rg -n '\b(shell_exec|exec|proc_open|passthru|system)\s*\('
     lanes --glob '*.php'` found only PDO `exec()` calls in Syncthing's SQLite
     checkpoint store, not process shell-outs.
   - Evidence: the accounting risk is instead static, copied, supplied, and
     generated evidence being blended into progress percentages without a
     normalized native-parity denominator.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Most recent active exact gate output observed during audit validation:

```text
1983399 php tools/run-tests.php
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
1983399 claude  1983362  00:07   R    php tools/run-tests.php
```

No duplicate root harness was started. The tree also failed the stability gate:
active lane agents, watchdog/status publisher loops, evaluator/capacity
dashboard loops, uncommitted lane batches, stale stopped-lane metadata, and a
broad dirty worktree mean a root result would not represent one accepted
snapshot.

A later post-commit exact gate returned no rows, but no root run was started
because the stability gate still failed.

Validation commands run included:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
jq summary reads over every lanes/*/UPSTREAM_TEST_MANIFEST.json
jq summary reads over every lanes/*/lane-status.json
git log --oneline --name-only -n 5
git status --short
git status --short --untracked-files=no
git diff --shortstat
git rev-parse --short=12 HEAD
pgrep -af '^php tools/run-tests\.php( |$)'
ps -eo pid,user,ppid,etime,stat,args
rg -n '\b(shell_exec|exec|proc_open|passthru|system)\s*\(' lanes --glob '*.php'
```

## Next Best Step

Freeze active writers and status publishers, stop duplicate root/focused PHP
loops, validate manifests from one frozen tree, accept or reject dirty lane
batches one lane at a time, normalize denominator/mapped/PHP pass/runner/commit
fields, regenerate `progress.md`, `porting.html`, `porting-summary.json`, and
lane statuses from that same accepted snapshot, then rerun the exact
duplicate-root gate and capture one quiesced root `php tools/run-tests.php` run
if the gate remains empty.
