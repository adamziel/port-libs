# Independent Audit - 2026-05-23T16:04:04Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, active process state, dirty
tree state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions,
push, read secrets, inspect process environments, credential stores, provider
configs, or auth files, or start a root harness. Bridge code, generated
fixtures, supplied fixtures, shell-outs, live-provider anecdotes, and plan-only
workflow artifacts are treated as non-progress unless explicitly temporary
oracle tooling.

Sampled `HEAD`: `01d192b950a5` (`Refresh independent audit status`). Recent
history is still audit-only: the latest 12 sampled commits all have subject
`Refresh independent audit status` and touch only `audits/latest.md` and
`progress.md`.

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json` passed
at this sample.

## Manifest/Status Snapshot

| Lane | Manifest denominator | Manifest mapped | Status PHP pass/fail | Status estimate | Commit/status field |
| --- | ---: | ---: | ---: | ---: | --- |
| difftastic | prose `604...` | 297 | 296 / 0 | 95% | pending in shared dirty worktree |
| dolt | prose `613...` | 571 | 309 / 0 | 95% | not committed |
| esbuild | prose `2,567...` | 255 | 255 / 0 | 77% | uncommitted |
| gitoxide | 2877 | 2092 | 3918 / 0 | 98% | pending |
| libsqlite | 1589 | 244 | 244 / 0 | 98% | uncommitted |
| lightningcss | 3532 | 1362 | 1536 / 0 | 86% | uncommitted; says `HEAD 01d192b9` |
| markerPDF | 289 | 237 | 360 / 0 | 95% | uncommitted |
| pandoc | prose `2276...` | 744 | 231 / 0 | 98% | pending |
| quadrable | prose `55...` | 55 | 152 / 0 | 99% | pending lane batch |
| rclone | 1601 | 524 | 524 / 0 | 98% | pending lane-local changes |
| readability | 1984 | 1804 | 162 / 0 | 98% | uncommitted |
| syncthing | 658 | 431 | 3029 / 0 | 98% | pending |

## Findings

1. **Critical - the repository still has no stable integration snapshot suitable for an accepted root-test baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `progress.md:345`, `.tmux-team/prompts/*`,
     `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires capped supervised lane work,
     current owner/session tracking, small committed slices with passing
     tests, periodic repo-wide tests, and honest failure recording.
   - Evidence: `progress.md:25` still documents a target of two
     implementation lanes plus one auditor, while `progress.md:31` through
     `progress.md:42` still reports every lane as `stopped`.
   - Evidence: process sampling found active worker/status loops including
     `run-tmux-agent` PIDs `2002880`, `2128917`, `2147315`, `2209178`,
     `2218606`, `2218799`, `2220185`, `2220386`, `2222778`, `2222892`,
     `2224848`, `2224907`, `2228322`, and `2228549`, plus dashboard updater
     PID `2222131`, team watchdog PID `2347911`, evaluator PID `2424048`,
     and capacity controller PID `2452997`.
   - Evidence: latest dirty samples reported `2097` default
     `git status --short` rows, `201` tracked dirty rows, `2141`
     all-untracked rows, and `201 files changed, 83487 insertions(+),
     6502 deletions(-)`.
   - Audit judgment: even with the exact no-argument root-test gate clear at
     this sample, `php tools/run-tests.php` would not describe one accepted
     snapshot while writers and status publishers are active against this
     broad dirty tree.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and fail the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:50`,
     `porting.html:54` through `porting.html:65`,
     `porting-summary.json:2` through `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md` requires a generated dashboard with
     average progress and per-lane columns for library, suite progress,
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32` and `porting-summary.json:2` still publish
     `2026-05-23 04:57:16 UTC`; `porting.html:33`,
     `porting.html:36`, `porting-summary.json:3`, and
     `porting-summary.json:5` still identify source snapshot `bda83c6b93d4`,
     while sampled `HEAD` is `01d192b950a5`.
   - Evidence: published mapped counts disagree with current manifests for
     every lane except Quadrable: Difftastic is published as `160 / 417`
     while current manifest is `297 / 604`; Dolt is `242 / 613` while
     current is `571 / 613`; Gitoxide is `1432 / 2877` while current is
     `2092 / 2877`; rclone is `291 / 327` while current is `524 / 1601`;
     Syncthing is `235 / 658` while current is `431 / 658`.
   - Evidence: `porting.html:41` through `porting.html:50` still combine
     benchmark source with denominator and PHP pass/fail with mapped tests, so
     the dashboard does not expose the separate columns required by the goal.

3. **High - `progress.md` does not describe the active system.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `progress.md:345`, `lanes/*/lane-status.json:4`,
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include
     active lanes, current owner/session, next task per lane, percentage
     estimates, latest commit, and blockers.
   - Evidence: the active-lane table still shows stale stopped sessions and
     estimates from `5%` to `66%`, while current lane statuses report `77%`
     to `99%` and active process sampling shows many lanes running.
   - Evidence: `progress.md:345` already records the same stale-dashboard,
     active-writer, non-comparable-count, and pending-dirty-batch blocker from
     the prior audit, but the active-lane table and generated dashboard remain
     unreconciled.

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
   - Goal requirement at risk: `goal.md` requires small, reviewable slices
     with passing tests and latest commit tracking per lane.
   - Evidence: every lane's latest-commit/status field says `pending`,
     `not committed`, `uncommitted`, `pending lane batch`, or dirty-worktree
     prose. LightningCSS embeds the sampled audit `HEAD`, but still says the
     lane batch is uncommitted.
   - Evidence: the latest 12 sampled commits are audit-only refresh commits;
     none integrate the current lane batches described by status files.

5. **High - near-complete percentages and "no blocker" language overstate upstream parity.**
   - Paths: `lanes/*/lane-status.json:4`,
     `lanes/*/lane-status.json:12`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of
     truth, passing tests are not enough, and hard features must be marked as
     blockers or future slices rather than silently skipped.
   - Evidence: ten lanes are marked `95%` to `99%`. Those estimates coexist
     with uncommitted lane work, no accepted aggregate root verification, stale
     dashboard output, skipped full upstream runners, and explicit future gaps
     such as Gitoxide full Cargo parity, markerPDF live Python/model
     workflows, rclone live-provider/mount parity, Pandoc full Haskell runner,
     Syncthing full upstream Go runner, and Quadrable full sync-fuzzer and
     benchmark runs.
   - Audit judgment: focused native slices are useful, but the percentages
     read as near-native parity while accepted-commit and root-test evidence
     does not support that claim.

6. **High - manifest/status count schemas are still not normalized or comparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/lane-status.json:6`,
     `lanes/lightningcss/lane-status.json:6`,
     `lanes/readability/lane-status.json:6`,
     `lanes/syncthing/lane-status.json:6`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator, mapped upstream tests, PHP passing/failing counts, and a
     dashboard whose rows can be compared across lanes.
   - Evidence: denominator `total` is numeric in some manifests and prose in
     others; mapped units and PHP pass units are not comparable. Gitoxide maps
     `2092` units but reports `3918` PHP passes; LightningCSS maps `1362` but
     reports `1536` PHP passes; Readability maps `1804` but reports `162` PHP
     passes; Syncthing maps `431` but reports `3029` PHP passes.
   - Evidence: Difftastic now has a manifest/status internal count mismatch:
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14` says `604` inspected
     artifacts and `:15` says `297` mapped, while
     `lanes/difftastic/lane-status.json:5` says `603` artifacts and
     `lanes/difftastic/lane-status.json:6` says `296` PHP passes.

7. **Medium - evidence accounting still blends non-native, supplied, copied, live-provider-skipped, and plan-only artifacts with native parity.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/lane-status.json:5`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/rclone/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` says generated fixtures, bridge
     calls, and shell-outs must not count as native implementation progress
     unless explicitly temporary oracle tooling.
   - Evidence: markerPDF counts supplied-document excerpts, package/lock
     metadata, workflow plans, helper scripts, Streamlit/Python/model planning,
     and `runnerStatus`-style non-execution while still claiming `95%`;
     Readability counts copied Mozilla fixture/oracle coverage while PHP
     behavior tests are a much smaller unit; rclone records live
     provider/mount parity as explicitly open while still claiming `98%`.
   - Audit judgment: the risk is not obvious shelling out in lane PHP; it is
     progress accounting that makes non-native or unexecuted evidence look too
     much like completed native parity.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

The exact duplicate-root gate returned no rows at the audit samples. No root
run was started because the stability gate failed: active lane agents,
watchdog/status publishers, dashboard/evaluator/capacity/integrator loops,
pending dirty lane batches, recent audit-only history, and stale generated
dashboards persisted.

Validation commands run included:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
jq summary reads over every lanes/*/UPSTREAM_TEST_MANIFEST.json
jq summary reads over every lanes/*/lane-status.json
git log --oneline --name-only -n 12
git status --short
git status --short --untracked-files=no
git status --short --untracked-files=all
git diff --shortstat
git rev-parse --short=12 HEAD
pgrep -af '^php tools/run-tests\.php( |$)'
pgrep -af 'run-tmux-agent|run-team-watchdog|run-capacity-controller|run-dashboard-updater|run-evaluator|run-integrator|run-tests\.php|go test|bats|testrunner\.tcl|cargo test|npm test'
nl -ba progress.md
nl -ba porting.html
nl -ba porting-summary.json
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
