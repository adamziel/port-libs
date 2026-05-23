# Independent Audit - 2026-05-23T15:21:24Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, active process state, dirty
tree state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions,
push, read secrets, inspect process environments, or start a root harness.
Bridge code, generated fixtures, supplied fixtures, and shell-backed evidence
are treated as non-progress unless explicitly temporary oracle tooling.

Sampled `HEAD`: `8980be1ebb5e` (`Refresh independent audit status`).
Recent Git history sampled by `git log --oneline --stat -n 8` is still
audit-only `Refresh independent audit status` commits touching
`audits/latest.md` and `progress.md`, while lane implementation, manifest, and
status files remain broadly dirty.

Manifest/status snapshot:

| Lane | Manifest denominator | Mapped | Lane PHP pass/fail | Lane estimate | Commit field |
| --- | ---: | ---: | ---: | ---: | --- |
| difftastic | string `598 inspected...` | 292 | 291 / 0 | 95% | pending dirty worktree |
| dolt | string `613 upstream...` | 558 | 305 / 0 | 94% | not committed |
| esbuild | string `2,567 counted...` | 244 | 244 / 0 | 76% | uncommitted |
| gitoxide | 2877 | 2001 | 3816 / 0 | 98% | pending |
| libsqlite | 1589 | 239 | 238 / 0 | 98% | uncommitted |
| lightningcss | 3532 | 1315 | 1484 / 0 | 86% | dirty worktree |
| markerPDF | 285 | 233 | 352 / 0 | 94% | uncommitted |
| pandoc | string `2276 upstream...` | 716 | 227 / 0 | 96% | pending |
| quadrable | string `55 tracked...` | 55 | 149 / 0 | 99% | pending |
| rclone | 1601 | 517 | 517 / 0 | 98% | pending |
| readability | 1984 | 1772 | 160 / 0 | 98% | uncommitted |
| syncthing | 658 | 381 | 381 / 0 | 98% | pending |

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
   - Evidence: `progress.md:25` still documents a launch target of two
     implementation lanes plus one auditor, while `progress.md:31` through
     `progress.md:42` reports every lane session as `stopped`.
   - Evidence: process sampling found active `run-tmux-agent.sh` sessions for
     Pandoc, Dolt runner, LightningCSS, rclone, Dolt, libsqlite, markerPDF,
     Readability, Difftastic, Gitoxide, Quadrable, auditor, integrator,
     Syncthing, and esbuild, plus team watchdog, evaluator,
     capacity-controller, and dashboard-updater loops.
   - Evidence: dirty-tree samples moved from `1945` default
     `git status --short` rows and `192` tracked dirty rows to `1972` default
     rows and `197` tracked dirty rows; latest shortstat was
     `197 files changed, 76332 insertions(+), 7477 deletions(-)`.
   - Evidence: a validation pass briefly saw `jq` reject
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json` during active writes; a
     later full `jq empty` pass succeeded after the file changed again.
   - Audit judgment: a no-argument `php tools/run-tests.php` result would not
     represent one accepted snapshot while writers and status publishers are
     active against a broad dirty tree.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and do not satisfy the dashboard contract.**
   - Paths: `porting.html:30`, `porting.html:36`, `porting.html:41`,
     `porting.html:65`, `porting-summary.json:2`, `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md` requires a generated dashboard with
     average progress and per-lane columns for library, suite progress,
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:30` still publishes average progress `68.8%`;
     `porting.html:32` and `porting-summary.json:2` still publish generated
     time `2026-05-23 04:57:16 UTC`; `porting.html:33`,
     `porting.html:36`, and `porting-summary.json:3` through
     `porting-summary.json:8` still point at snapshot/source commit
     `bda83c6b93d4`, while sampled `HEAD` is `8980be1ebb5e`.
   - Evidence: published rows disagree with current manifests and lane
     statuses. Difftastic is published as `55%`, `160 / 417`, and
     `160 pass` at `porting.html:54`, while current files report `95%`,
     mapped `292`, and `291` PHP passes. rclone is published as denominator
     `327`, mapped/pass `291` at `porting.html:63`, while current files report
     denominator `1601`, mapped `517`, and `517` PHP passes. markerPDF is
     published as denominator `78`, mapped `159`, and `264 pass` at
     `porting.html:60`, while current files report denominator `285`, mapped
     `233`, and `352` PHP passes.
   - Evidence: `porting.html:41` through `porting.html:50` still collapse
     benchmark source, upstream denominator, mapped tests, and PHP pass/fail
     into compact cells rather than first-class contract columns.

3. **High - `progress.md`, lane statuses, and recent history do not describe accepted implementation progress.**
   - Paths: `progress.md:31`, `progress.md:42`, `lanes/*/lane-status.json`,
     recent Git history.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include
     active lanes, current owner/session, next task per lane, percentage
     estimates, latest commit, and small reviewable committed slices.
   - Evidence: `progress.md:31` through `progress.md:42` still lists stopped
     lane estimates from `5%` to `66%`, while current lane statuses claim
     `76%` to `99%`.
   - Evidence: current `latestCommit` fields are mostly not accepted commits:
     examples include `pending in shared dirty worktree`, `not committed`,
     `uncommitted port-esbuild lane batch`, `pending`, and `pending
     lane-local changes`.
   - Evidence: the latest sampled eight commits are all audit-only refreshes
     touching `audits/latest.md` and `progress.md`, not integration commits for
     these claimed lane slices.

4. **High - manifest denominator, mapped-test, PHP-pass, and status units are still non-normalized.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/*/lane-status.json:4`, `lanes/*/lane-status.json:6`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator, mapped upstream tests, PHP passing/failing counts, and
     dashboard fields that can be compared across lanes.
   - Evidence: five manifests still use prose strings for
     `benchmarkDenominator.total` instead of numeric denominators:
     Difftastic, Dolt, esbuild, Pandoc, and Quadrable.
   - Evidence: mapped units and PHP pass units are mixed as if they were one
     measure. Difftastic reports `292` mapped units and `291` PHP passes;
     Gitoxide reports `2001` mapped units and `3816` PHP passes; libsqlite
     reports `239` mapped and `238` PHP passes; LightningCSS reports `1315`
     mapped and `1484` PHP passes; markerPDF reports `233` mapped and `352`
     PHP passes; Readability reports `1772` mapped upstream checks and `160`
     PHP passes.
   - Evidence: internal status text also drifts from manifests: markerPDF
     status audit text still references `284 total / 232 mapped` while the
     manifest now reports `285 / 233`.

5. **High - near-complete percentages mask explicit upstream-runner and hard-feature gaps.**
   - Paths: `lanes/gitoxide/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:4`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of
     truth, passing tests are not enough, and hard features must be marked as
     blockers or future slices rather than silently skipped.
   - Evidence: Gitoxide, libsqlite, rclone, Readability, and Syncthing are
     reported at `98%`, Pandoc at `96%`, markerPDF at `94%`, and Quadrable at
     `99%`, while their own blockers/audit text still describe major
     non-parity: full Cargo workspace not executed, full SQLite all/release
     permutations out of scope, full upstream benchmarks/model workflows not
     executed, full Pandoc Haskell runner unexecuted, live rclone provider/mount
     parity open, and full Syncthing `go test ./...` unexecuted.

6. **Medium - root-test and verification records still do not describe one accepted integration checkpoint.**
   - Paths: `lanes/dolt/lane-status.json:10`, `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:10`, `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`, `progress.md` audit history.
   - Goal requirement at risk: `goal.md` requires periodic repo-wide tests and
     honest failure recording.
   - Evidence: lane statuses mix focused-lane green results, upstream bounded
     runners, pending aggregate verification, duplicate-root skips, and stale
     red/green root anecdotes. These may be locally true, but they are not a
     single frozen-tree `php tools/run-tests.php` baseline.
   - Evidence: `progress.md` already records repeated independent audits where
     exact root gates alternated between clear and active while the tree
     remained dirty and status publishers kept moving files.

7. **Medium - direct PHP shell-bridge risk is low by scan, but generated/supplied evidence still needs stricter accounting.**
   - Paths: `lanes/syncthing/src/SqliteCheckpointStore.php:21`,
     `lanes/syncthing/src/SqliteCheckpointStore.php:186`,
     `lanes/syncthing/src/SqliteCheckpointStore.php:195`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`.
   - Goal requirement at risk: `goal.md` says generated fixtures, bridge
     calls, or shell-outs must not count as native implementation progress
     unless explicitly temporary oracle tooling.
   - Evidence: `rg -n '\b(shell_exec|exec|proc_open|passthru|system)\s*\('
     lanes --glob '*.php'` found only PDO `exec()` calls in
     `SqliteCheckpointStore.php`, not process shell-outs.
   - Evidence: the current accounting risk is instead static/generated/supplied
     evidence being blended into progress percentages for markerPDF,
     Readability, and rclone without a normalized separation from native parity.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Early exact gate samples returned no rows, but a later required gate found an
active root harness:

```text
1942101 php tools/run-tests.php
```

Owner evidence:

```text
PID USER        PPID STAT ELAPSED COMMAND
1942101 claude   1941976 R    00:30 php tools/run-tests.php
```

I did not start a duplicate root harness. The tree was also not stable enough
for this auditor to start or accept a root run: active lane agents,
watchdog/status publisher loops, evaluator/capacity/dashboard loops,
stopped-lane progress metadata, uncommitted lane batches, and a broad dirty
worktree mean a root result would not represent one accepted snapshot.

Validation commands run:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
jq -r '...' lanes/*/UPSTREAM_TEST_MANIFEST.json
jq -r '...' lanes/*/lane-status.json
git log --oneline --decorate --stat -n 8 -- . ':(exclude).git'
git status --short
git status --short --untracked-files=no
git diff --shortstat
git rev-parse --short=12 HEAD
pgrep -af '^php tools/run-tests\.php( |$)'
ps -eo pid=,ppid=,stat=,etime=,comm=,args=
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
