# Independent Audit - 2026-05-23T16:52:02Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, current worktree state, active
process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied fixture oracles,
shell-outs, live-provider anecdotes, and plan-only workflow artifacts are
treated as non-progress unless explicitly temporary oracle tooling.

Sampled `HEAD`: `532bf35fcb4e` (`Refresh independent audit status`). The latest
41 sampled commits are still audit-only `Refresh independent audit status`
commits before the nearest recent lane implementation commit.

`jq` summary reads over every manifest/status file completed. `pgrep -af
'^php tools/run-tests\.php( |$)'` returned no rows at the initial sample, but
the tree was not stable enough for a trustworthy no-argument root run. A later
handoff gate found active root PID `2697291`, owned by `claude`, running
`php tools/run-tests.php`.

## Manifest/Status Snapshot

| Lane | Current manifest denominator | Manifest mapped | Status PHP pass/fail | Status estimate | Commit/status field |
| --- | ---: | ---: | ---: | ---: | --- |
| difftastic | 613 inspected behavior artifacts | 306 | 306 / 0 | 96% | pending in shared dirty worktree |
| dolt | 613 executable test files | 588 | 315 / 0 | 95% | not committed |
| esbuild | 2,567 upstream entry points | 261 | 261 / 0 | 77% | uncommitted |
| gitoxide | 2,877 upstream files | 2,169 | 4,069 / 0 | 98% | pending |
| libsqlite | 1,589 | 251 | 250 / 0 | 98% | uncommitted |
| lightningcss | 3,532 behavior checks | 1,418 | 1,607 / 0 | 88% | uncommitted |
| markerPDF | 293 static behavior/reference units | 241 | 367 / 0 | 96% | uncommitted |
| pandoc | 2,276 inspected artifacts | 786 | 237 / 0 | 98% | pending |
| quadrable | 55 tracked paths plus runner notes | 55 | 160 / 0 | 99% | pending lane batch |
| rclone | 1,601 Go test/benchmark/example units | 540 | 540 / 0 | 98% | pending lane-local changes |
| readability | 1,984 Mocha tests | 1,885 | 168 / 0 | 98% | uncommitted; references old `a737c8b1` |
| syncthing | 658 Go entry points | 452 | 3,151 / 0 | 98% | pending |

## Findings

1. **Critical - there is still no stable integration snapshot suitable for an accepted root-test baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `.tmux-team/prompts/*`, `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires capped supervised lane work,
     current owner/session tracking, small committed slices with passing tests,
     periodic repo-wide tests, and honest failure recording.
   - Evidence: `progress.md:25` still documents a target of two
     implementation lanes plus one auditor, and `progress.md:31` through
     `progress.md:42` reports every lane as `stopped`.
   - Evidence: active process sampling found 23 repo worker/status processes,
     including the dashboard updater, team watchdog, evaluator, capacity
     controller, integrator, auditor, Dolt runner, all 12 lane agents, and
     several capacity agents.
   - Evidence: the worktree is not quiescent. Latest samples reported `217`
     tracked dirty rows, `2,328` total status rows including untracked files,
     and `217 files changed, 92594 insertions(+), 7352 deletions(-)`.
   - Evidence: the exact duplicate-root gate returned no rows at this sample,
     but a no-argument root run would not describe one accepted snapshot while
     active writers/status publishers and broad dirty batches remain in flight.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and fail the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:65`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `porting-summary.json:11` through `porting-summary.json:213`.
   - Goal requirement at risk: `goal.md` requires a generated dashboard with
     average progress and per-lane columns for library, suite progress,
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32` and `porting-summary.json:2` still say
     generated `2026-05-23 04:57:16 UTC`; `porting.html:33`,
     `porting.html:36`, and `porting-summary.json:3` still identify source
     snapshot `bda83c6b93d4`, while sampled `HEAD` is `532bf35fcb4e`.
   - Evidence: published rows disagree with current manifests/status files.
     Difftastic is published as `160 / 417`, current is `306 / 613`; Dolt is
     `242 / 613`, current is `588 / 613`; esbuild is `164 / 2567`, current is
     `261 / 2567`; Gitoxide is `1432 / 2877`, current is `2169 / 2877`;
     libsqlite is `149 / 1454`, current is `251 / 1589`; LightningCSS is
     `773 / 3532`, current is `1418 / 3532`; markerPDF is `159 / 78`, current
     is `241 / 293`; Pandoc is `426 / 2028`, current is `786 / 2276`; rclone is
     `291 / 327`, current is `540 / 1601`; Readability is `1031 / 1984`,
     current is `1885 / 1984`; Syncthing is `235 / 658`, current is
     `452 / 658`.
   - Evidence: `porting.html:41` through `porting.html:50` still omits separate
     upstream-denominator and PHP pass/fail columns; `porting.html:54` through
     `porting.html:65` mixes PHP pass/fail with mapped coverage in one cell.

3. **High - `progress.md` does not describe the active system.**
   - Paths: `progress.md:14`, `progress.md:25`, `progress.md:31` through
     `progress.md:42`, `lanes/*/lane-status.json:4`,
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include
     active lanes, current owner/session, next task per lane, percentage
     estimates, latest commit, and blockers.
   - Evidence: the active-lane table still shows all sessions as `stopped` with
     estimates from `5%` to `66%`, while current lane status files report
     `77%` to `99%` and process sampling shows active lane agents running.
   - Evidence: `progress.md:14` still leaves the independent auditor loop
     unchecked even though audit refresh commits continue and a `port-auditor`
     watchdog process is active.
   - Evidence: the coordination table still contains stale next tasks such as
     Gitoxide SOCKS/proxy work, LightningCSS grid/CSSOM work, and Dolt idle
     deferral, while current lane statuses describe different pending batches.

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
   - Goal requirement at risk: `goal.md` requires small, reviewable slices with
     passing tests and latest commit tracking per lane.
   - Evidence: every lane's latest-commit field says `pending`, `not
     committed`, `uncommitted`, or dirty-worktree prose. Readability still
     references observed `HEAD a737c8b1`, while sampled `HEAD` is
     `532bf35fcb4e`.
   - Evidence: the latest 41 sampled commits are audit-only refresh commits;
     the nearest recent implementation commit is still behind those audit-only
     updates, while lane-status files describe much newer uncommitted work.

5. **High - near-complete percentages and "no blocker" language overstate accepted upstream parity.**
   - Paths: `lanes/*/lane-status.json:4`, `lanes/*/lane-status.json:12`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of
     truth, passing tests are not enough, and hard features must be marked as
     blockers or future slices rather than silently skipped.
   - Evidence: ten lanes are marked `95%` to `99%`. Those estimates coexist
     with uncommitted lane work, no accepted aggregate root verification, stale
     generated dashboards, and explicit future gaps such as Gitoxide full Cargo
     parity, markerPDF live Python/model workflows, rclone live-provider and
     mount parity, Pandoc full Haskell runner, Syncthing full Go runner,
     SQLite all/release permutations, and Quadrable full sync-fuzzer and
     benchmark runs.
   - Audit judgment: focused native slices are useful, but these percentages
     read as near-native parity while accepted-commit and root-test evidence
     does not support that claim.

6. **High - manifest/status count schemas are still not normalized or comparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/lane-status.json:6`,
     `lanes/readability/lane-status.json:6`,
     `lanes/syncthing/lane-status.json:6`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator, mapped upstream tests, PHP passing/failing counts, and a
     dashboard whose rows can be compared across lanes.
   - Evidence: denominator `total` is numeric in some manifests and prose in
     others. The mapped unit differs by lane: inspected artifacts, executable
     test files, static behavior units, upstream test functions, copied fixture
     checks, or local behavior checks.
   - Evidence: PHP pass units are not comparable. Gitoxide maps `2,169`
     upstream units but reports `4,069` PHP passes; Syncthing maps `452` but
     reports `3,151` PHP passes; Readability maps `1,885` but reports only
     `168` PHP passes; markerPDF maps `241` but reports `367` PHP passes.

7. **Medium - progress accounting still blends non-native, copied, live-provider-skipped, and plan-only artifacts with native parity.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` says generated fixtures, bridge
     calls, and shell-outs must not count as native implementation progress
     unless explicitly temporary oracle tooling.
   - Evidence: markerPDF counts workflow plans, package/lock metadata,
     dependency graph evidence, benchmark/Nougat plan-only subprocess metadata,
     Streamlit/FastAPI planning, remote polling plans, and supplied-document
     excerpts while live Python/model execution remains blocked and
     `runnerStatus` is `not-executed`.
   - Evidence: Readability count growth is dominated by copied Mozilla
     fixture/oracle coverage: 113 copied fixture pages and 1,885 mapped checks,
     while native PHP behavior tests are 168. rclone explicitly leaves live
     provider/mount parity open while reporting `98%`.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

The gate returned no rows at the initial sample. No root run was appropriate
because the tree was not stable enough: active lane agents, watchdog/status
publishers, evaluator/capacity/integrator jobs, pending dirty lane batches,
audit-only recent history, and stale generated dashboards persisted. A later
handoff gate returned active root PID `2697291`; owner evidence was:

```text
2697291 claude 2697245 00:19 R php tools/run-tests.php
```

Validation commands run included:

```text
jq summary reads over every lanes/*/UPSTREAM_TEST_MANIFEST.json
jq summary reads over every lanes/*/lane-status.json
sed and nl reads of goal.md, progress.md, porting.html, porting-summary.json, audits/latest.md
git log --oneline --decorate -n 50
git show --stat --oneline --decorate -n 8
git status --short --untracked-files=no
git status --short --untracked-files=all
git diff --shortstat
git rev-parse --short=12 HEAD
pgrep -af '^php tools/run-tests\.php( |$)'
pgrep -af 'scripts/run-tmux-agent|scripts/run-dashboard-updater-loop|scripts/run-evaluator-loop|scripts/run-team-watchdog|scripts/run-capacity-controller-loop|scripts/run-integrator-loop|scripts/run-artifact|php tools/run-tests\.php'
ps -o pid,user,ppid,etime,stat,args -p 2697291
```

## Next Intervention

Freeze active writers/status publishers and duplicate focused/root PHP loops,
then accept or reject dirty lane batches one lane at a time. From that frozen
tree, normalize manifest/status denominator, mapped, PHP pass/fail, runner,
blocker, and commit fields; regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the same accepted snapshot; rerun
the exact duplicate-root gate; and capture one quiesced root
`php tools/run-tests.php` run only if the gate remains empty.
