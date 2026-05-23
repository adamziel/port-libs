# Independent Audit - 2026-05-23T15:37:36Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, active process state, dirty
tree state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions,
push, read secrets, inspect process environments, credential stores, provider
configs, or auth files, or start a root harness. Bridge code, generated
fixtures, supplied fixtures, shell-outs, live-provider anecdotes, and upstream
runner cache artifacts are treated as non-progress unless explicitly temporary
oracle tooling.

Sampled `HEAD`: `61db77dc41aa` (`Refresh independent audit status`). Recent
history is still audit-only: the latest 20 sampled commits are all `Refresh
independent audit status` commits touching only `audits/latest.md` and
`progress.md`.

Manifest/status snapshot:

| Lane | Manifest denominator | Manifest mapped | Status PHP pass/fail | Status estimate | Commit/status field |
| --- | ---: | ---: | ---: | ---: | --- |
| difftastic | prose `599 inspected...` | 294 | 292 / 0 | 95% | pending in shared dirty worktree |
| dolt | prose `613 upstream...` | 562 | 305 / 0 | 95% | not committed |
| esbuild | prose `2,567 counted...` | 247 | 247 / 0 | 76% | uncommitted |
| gitoxide | 2877 | 2009 | 3838 / 0 | 98% | pending |
| libsqlite | 1589 | 240 | 240 / 0 | 98% | uncommitted |
| lightningcss | 3532 | 1328 | 1490 / 0 | 86% | uncommitted; field still names `HEAD 2799d6e1` |
| markerPDF | 286 | 234 | 357 / 0 | 94% | uncommitted |
| pandoc | prose `2276 upstream...` | 722 | 228 / 0 | 97% | pending |
| quadrable | prose `55 tracked...` | 55 | 149 / 0 | 99% | pending lane batch |
| rclone | 1601 | 520 | 520 / 0 | 98% | pending lane-local changes |
| readability | 1984 | 1789 | 161 / 0 | 98% | uncommitted |
| syncthing | 658 | 401 | 2922 / 0 | 98% | pending |

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
     implementation lanes plus one auditor, while `progress.md:31` through
     `progress.md:42` reports every lane session as `stopped`.
   - Evidence: process sampling found active worker/status loops despite that
     table: `run-tmux-agent` PIDs `1983551`, `1984583`, `1985770`,
     `1985872`, `1985996`, `1986318`, `1989559`, `1992015`, `1993979`,
     `1996241`, `1996320`, `1999022`, `2000310`, `2000397`, and `2000504`,
     plus `run-team-watchdog.sh` PID `2347911`, `run-evaluator-loop.sh` PID
     `2424048`, `run-capacity-controller-loop.sh` PID `2452997`, and
     `run-dashboard-updater-loop.sh` PID `2479222`.
   - Evidence: the latest dirty-tree sample reported `2040` default
     `git status --short` rows, `199` tracked dirty rows, and
     `199 files changed, 79435 insertions(+), 7349 deletions(-)`.
   - Audit judgment: a no-argument `php tools/run-tests.php` result would not
     represent one accepted snapshot while writers and status publishers are
     active against this broad dirty tree.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and do not satisfy the dashboard contract.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:33`,
     `porting.html:36`, `porting.html:54`, `porting.html:60`,
     `porting.html:63`, `porting.html:65`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md` requires a generated dashboard with
     average progress and per-lane columns for library, suite progress,
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32` still publishes
     `2026-05-23 04:57:16 UTC`; `porting.html:33` and `porting.html:36`
     still identify snapshot `bda83c6b93d4`, while sampled `HEAD` is
     `61db77dc41aa`.
   - Evidence: published rows disagree with current manifests/status files:
     Difftastic is published as `160 / 417` mapped and `160 pass` at
     `porting.html:54`, while current files report `294 / 599` mapped and
     `292 pass`. markerPDF is published as `159 / 78` mapped and `264 pass`
     at `porting.html:60`, while current files report `234 / 286` mapped and
     `357 pass`. rclone is published as `291 / 327` mapped and `291 pass` at
     `porting.html:63`, while current files report `520 / 1601` mapped and
     `520 pass`. Syncthing is published as `235 / 658` mapped and `235 pass`
     at `porting.html:65`, while current files report `401 / 658` mapped and
     `2922 pass`.
   - Evidence: the dashboard still combines benchmark source/denominator and
     mapped/PHP pass data into compact cells instead of the separate goal
     columns needed for audit comparison.

3. **High - `progress.md` no longer describes the active system or current lane progress.**
   - Paths: `progress.md:25`, `progress.md:31`, `progress.md:42`,
     `lanes/*/lane-status.json:4`, `lanes/*/lane-status.json:6`,
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include
     active lanes, current owner/session, next task per lane, percentage
     estimates, latest commit, and blockers.
   - Evidence: the active-lane table still shows stale stopped sessions and
     old estimates from `5%` to `66%`, while current lane-status estimates
     are now `76%` to `99%`.
   - Evidence: active lane-agent and status-publisher processes contradict
     the stopped-lane table.
   - Evidence: most lane-status `latestCommit` fields are not commit hashes;
     they are pending/uncommitted dirty-batch handoffs.

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
   - Goal requirement at risk: `goal.md` requires small, reviewable slices
     with passing tests and latest commit tracking per lane.
   - Evidence: latest-commit fields include `pending`, `not committed`,
     `uncommitted`, `pending lane batch`, and dirty-worktree prose across all
     lanes.
   - Evidence: the latest 20 sampled commits are audit-only refreshes of
     `audits/latest.md` and `progress.md`; they do not integrate the current
     lane batches.

5. **High - near-complete percentages and "no blocker" language overstate parity.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:4`, `lanes/libsqlite/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:4`, `lanes/pandoc/lane-status.json:4`,
     `lanes/quadrable/lane-status.json:4`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/readability/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:4`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of
     truth, passing tests are not enough, and hard features must be marked as
     blockers or future slices rather than silently skipped.
   - Evidence: Gitoxide, libsqlite, pandoc, quadrable, rclone, readability,
     and syncthing are marked `97%` to `99%`; Difftastic and markerPDF are
     `95%` and `94%`. These claims coexist with uncommitted work, stale
     dashboard output, pending aggregate verification, skipped full upstream
     runners, and live/provider/heavy paths left unexecuted.
   - Audit judgment: focused slices are useful, but these percentages read as
     near-native parity while the manifests/status fields still describe major
     unexecuted upstream and integration surfaces.

6. **High - manifest/status count schemas remain non-normalized and internally inconsistent.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/difftastic/lane-status.json:6`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/syncthing/lane-status.json:6`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator, mapped upstream tests, PHP passing/failing counts, and a
     dashboard whose rows can be compared across lanes.
   - Evidence: denominator `total` is sometimes numeric and sometimes prose
     strings mixing files, tests, helper invocations, fixtures, packages, and
     benchmark entries.
   - Evidence: mapped units and PHP pass units are not comparable:
     Difftastic maps `294` but status reports `292` PHP passes; Gitoxide maps
     `2009` but reports `3838` PHP passes; Syncthing maps `401` but reports
     `2922` PHP passes.
   - Evidence: the schema shape itself varies: some manifests use structured
     object runner status and prose totals, making simple denominator/mapped
     extraction unreliable without lane-specific logic.

7. **Medium - evidence accounting still blends copied, supplied, generated, live-provider, and plan-only artifacts with native parity.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/lane-status.json:5`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/rclone/lane-status.json:5`.
   - Goal requirement at risk: `goal.md` says generated fixtures, bridge
     calls, and shell-outs must not count as native implementation progress
     unless explicitly temporary oracle tooling.
   - Evidence: markerPDF counts supplied-document excerpts, dependency
     graphs, model-runtime planning, and non-executed Python/model workflows;
     Readability counts copied Mozilla fixture/oracle evidence; rclone counts
     bounded upstream Go runner evidence while live provider/mount paths are
     explicitly skipped.
   - Audit judgment: the risk is not obvious shelling out in lane PHP; it is
     progress accounting that treats non-native evidence too much like
     completed native parity.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

The exact duplicate-root gate returned no rows at the audit sample. No root
run was started because the stability gate failed: active lane agents and
status publishers persisted, current lane batches are uncommitted,
`porting.html` is stale, recent history is audit-only, and the dirty tree is
too broad for a root result to describe one accepted snapshot.

Validation commands run included:

```text
jq summary reads over every lanes/*/UPSTREAM_TEST_MANIFEST.json
jq summary reads over every lanes/*/lane-status.json
git log --oneline --name-only -n 20
git status --short
git status --short --untracked-files=no
git diff --shortstat
git rev-parse --short=12 HEAD
pgrep -af '^php tools/run-tests\.php( |$)'
pgrep -af 'run-tmux-agent|run-team-watchdog|run-capacity-controller|run-dashboard-updater|run-evaluator|run-integrator|run-tests\.php|capacity|auditor|artifact|go test|bats|testrunner\.tcl|cargo test|npm test'
nl -ba progress.md
nl -ba porting.html
```

## Next Best Step

Freeze active writers and status publishers, stop duplicate root/focused PHP
loops, validate manifests from one frozen tree, accept or reject dirty lane
batches one lane at a time, normalize denominator/mapped/PHP pass/runner/commit
fields, regenerate `progress.md`, `porting.html`, `porting-summary.json`, and
lane statuses from that same accepted snapshot, then capture one quiesced root
`php tools/run-tests.php` run if the exact duplicate-root gate remains empty.
