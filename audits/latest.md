# Independent Audit - 2026-05-23T16:14:15Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, recent Git history, current worktree
state, active process state, and the required duplicate-root test gate. I also
sampled `lanes/*/lane-status.json` and `porting-summary.json` because they are
the records behind the dashboard/status claims.

I did not edit lane implementation files, launch agents or tmux sessions,
push, read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, supplied fixtures,
shell-outs, live-provider anecdotes, and plan-only workflow artifacts are
treated as non-progress unless explicitly temporary oracle tooling.

Sampled `HEAD`: `3a8f4f2650c0` (`Refresh independent audit status`). Recent
history remains audit-only: the latest 20 sampled commits all have subject
`Refresh independent audit status`.

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json` passed
at this sample.

## Manifest/Status Snapshot

| Lane | Manifest denominator | Manifest mapped | Status PHP pass/fail | Status estimate | Commit/status field |
| --- | ---: | ---: | ---: | ---: | --- |
| difftastic | 606 inspected artifacts | 300 | 297 / 0 | 95% | pending in shared dirty worktree |
| dolt | 613 upstream executable test files | 578 | 311 / 0 | 95% | not committed |
| esbuild | 2,567 upstream test entry points | 257 | 257 / 0 | 77% | uncommitted |
| gitoxide | 2877 | 2136 | 3965 / 0 | 98% | pending |
| libsqlite | 1589 | 246 | 246 / 0 | 98% | uncommitted |
| lightningcss | 3532 | 1372 | 1547 / 0 | 86% | uncommitted; says `HEAD 21c8222a` |
| markerPDF | 290 static behavior/reference units | 238 | 363 / 0 | 95% | uncommitted |
| pandoc | 2276 inspected artifacts | 761 | 233 / 0 | 98% | pending |
| quadrable | 55 tracked upstream paths plus prose runner notes | 55 | 153 / 0 | 99% | pending lane batch |
| rclone | 1601 static Go test/benchmark/example units | 527 | 527 / 0 | 98% | pending lane-local changes |
| readability | 1984 | 1821 | 163 / 0 | 98% | uncommitted |
| syncthing | 658 | 436 | 3064 / 0 | 98% | pending |

## Findings

1. **Critical - there is still no stable integration snapshot suitable for an accepted root-test baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `progress.md:346`, `.tmux-team/prompts/*`,
     `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires capped supervised lane work,
     current owner/session tracking, small committed slices with passing tests,
     periodic repo-wide tests, and honest failure recording.
   - Evidence: `progress.md:25` still documents a target of two implementation
     lanes plus one auditor, while `progress.md:31` through `progress.md:42`
     reports every lane as `stopped`.
   - Evidence: process sampling found 24 matching agent/status/test processes,
     including active lane agents for quadrable, lightningcss, difftastic,
     dolt, markerPDF, pandoc, rclone, libsqlite, readability, gitoxide,
     syncthing, auditor, integrator, dolt-runner, and capacity inventory work,
     plus dashboard updater PID `2222131`, team watchdog PID `2347911`,
     evaluator PID `2424048`, and capacity controller PID `2452997`.
   - Evidence: dirty samples moved during this audit: tracked dirty rows were
     `205`, all-status rows moved from `2189` to `2194`, and `git diff
     --shortstat` moved from `205 files changed, 85618 insertions(+), 6498
     deletions(-)` to `205 files changed, 86236 insertions(+), 6563
     deletions(-)`.
   - Evidence: the required duplicate-root gate at `2026-05-23T16:14:15Z`
     matched `2268233 php tools/run-tests.php`, `2268619 php
     tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests`, and
     `2268808 php tools/run-tests.php lanes/readability/tests`. Owner evidence
     captured the active no-argument root process as `2268233 claude 2268146
     00:28 R+ php tools/run-tests.php`; the focused PIDs exited before the
     owner sample.
   - Audit judgment: a new root run would have duplicated active root/focused
     PHP work and would not describe one accepted snapshot while writers and
     status publishers are active against this broad dirty tree.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and fail the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:65`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md` requires a generated dashboard with
     average progress and per-lane columns for library, suite progress,
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32` still says generated `2026-05-23 04:57:16
     UTC`; `porting.html:33` and `porting.html:36` still identify source
     snapshot `bda83c6b93d4`, while sampled `HEAD` is `3a8f4f2650c0`.
   - Evidence: published row counts disagree with current manifests/status
     files. Difftastic is published as `160 / 417` mapped while current
     manifest/status is `300 / 606` manifest-mapped and `297` PHP passes; Dolt
     is `242 / 613` while current is `578 / 613`; Gitoxide is `1432 / 2877`
     while current is `2136 / 2877`; markerPDF is `159 / 78` while current is
     `238 / 290`; Pandoc is `426 / 2028` while current is `761 / 2276`;
     rclone is `291 / 327` while current is `527 / 1601`; Syncthing is
     `235 / 658` while current is `436 / 658`.
   - Evidence: `porting.html:41` through `porting.html:50` still combine
     benchmark source with denominator and PHP pass/fail with mapped tests, so
     the required upstream-denominator and PHP-pass/fail columns are not
     separately exposed.

3. **High - `progress.md` does not describe the active system.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `lanes/*/lane-status.json:4`, `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include
     active lanes, current owner/session, next task per lane, percentage
     estimates, latest commit, and blockers.
   - Evidence: the active-lane table still shows all sessions as `stopped`
     with estimates from `5%` to `66%`, while current lane statuses report
     estimates from `77%` to `99%` and process sampling shows many lanes
     running.
   - Evidence: lane status files report new current work and pending batches,
     but the top coordination table still contains old next tasks such as
     Gitoxide SOCKS/proxy work, LightningCSS CSSOM/grid work, and Dolt idle
     deferral.

4. **High - claimed lane progress remains mostly unaccepted dirty-batch work, not small committed slices.**
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
   - Evidence: every lane's latest-commit/status field says `pending`, `not
     committed`, `uncommitted`, `pending lane batch`, or dirty-worktree prose.
     LightningCSS embeds stale `HEAD 21c8222a` while sampled `HEAD` is
     `3a8f4f2650c0`.
   - Evidence: the latest 20 sampled commits are audit-only refresh commits;
     none integrate the current lane batches described by status files.

5. **High - near-complete percentages and "no blocker" language overstate accepted upstream parity.**
   - Paths: `lanes/*/lane-status.json:4`,
     `lanes/*/lane-status.json:12`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of
     truth, passing tests are not enough, and hard features must be marked as
     blockers or future slices rather than silently skipped.
   - Evidence: ten lanes are marked `95%` to `99%`. Those estimates coexist
     with uncommitted lane work, active root/focused harnesses, no accepted
     aggregate root verification, stale generated dashboards, and explicit
     future gaps such as Gitoxide full Cargo parity, markerPDF live
     Python/model workflows, rclone live-provider/mount parity, Pandoc full
     Haskell runner, Syncthing full upstream Go runner, SQLite full all/release
     permutations, and Quadrable full sync-fuzzer and benchmark runs.
   - Audit judgment: focused native slices are useful, but the percentages read
     as near-native parity while accepted-commit and root-test evidence does
     not support that claim.

6. **High - manifest/status count schemas are still not normalized or comparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:583`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:593`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/lane-status.json:6`,
     `lanes/readability/lane-status.json:6`,
     `lanes/syncthing/lane-status.json:6`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator, mapped upstream tests, PHP passing/failing counts, and a
     dashboard whose rows can be compared across lanes.
   - Evidence: denominator `total` is numeric in some manifests and prose in
     others. The unit behind `mapped` differs by lane: inspected artifacts,
     executable test files, static behavior units, upstream test functions,
     copied fixture checks, or local behavior checks.
   - Evidence: PHP pass units are not comparable. Gitoxide maps `2136`
     upstream units but reports `3965` PHP passes; Syncthing maps `436` but
     reports `3064` PHP passes; Readability maps `1821` but reports only `163`
     PHP passes.
   - Evidence: Difftastic is internally inconsistent during active writes:
     the manifest now says `mapped: 300`, status says `297` PHP passes, and
     stale warning text still says `297` against `604` artifacts while the
     current denominator is `606`.

7. **Medium - progress accounting still blends non-native, copied, live-provider-skipped, and plan-only artifacts with native parity.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:309`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:533`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:900`,
     `lanes/gitoxide/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` says generated fixtures, bridge calls,
     and shell-outs must not count as native implementation progress unless
     explicitly temporary oracle tooling.
   - Evidence: markerPDF counts workflow plans, package/lock metadata,
     supplied-document excerpts, server/app plans, and benchmark preparation
     artifacts while runner status is `not-executed` and live Python/model
     workflows remain open.
   - Evidence: Readability count growth is dominated by copied Mozilla
     fixture/oracle coverage while PHP behavior tests remain a much smaller
     unit; rclone explicitly leaves live provider/mount parity open while
     reporting `98%`.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

The required gate matched active PHP harnesses during this audit:

```text
2268233 php tools/run-tests.php
2268619 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
2268808 php tools/run-tests.php lanes/readability/tests
```

Owner evidence captured for the active no-argument root process:

```text
2268233 claude 2268146 00:28 R+ php tools/run-tests.php
```

The focused PIDs exited before owner sampling. Later exact gates cleared, but
no root run was started because the duplicate-root gate had failed during the
audit and the broader stability gate remained failed: active lane agents,
watchdog/status/dashboard/evaluator/capacity/integrator loops, pending dirty
lane batches, audit-only recent history, and stale generated dashboards
persisted.

Validation commands run included:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
jq summary reads over every lanes/*/UPSTREAM_TEST_MANIFEST.json
jq summary reads over every lanes/*/lane-status.json
git log --oneline --name-only -n 12
git log --oneline -n 20
git status --short --untracked-files=no
git status --short --untracked-files=all
git diff --shortstat
git rev-parse --short=12 HEAD
pgrep -af '^php tools/run-tests\.php( |$)'
ps -o pid=,user=,ppid=,etime=,stat=,cmd= -p 2268233,2268619,2268808
pgrep -af 'run-tmux-agent|run-team-watchdog|run-capacity-controller|run-dashboard-updater|run-evaluator|run-integrator|run-tests\.php|go test|bats|testrunner\.tcl|cargo test|npm test'
nl -ba progress.md
nl -ba porting.html
rg line checks over lane manifests/status files
```

## Next Best Step

Freeze active writers/status publishers and duplicate root/focused PHP loops,
validate all manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced root `php tools/run-tests.php` run if the gate remains empty.
