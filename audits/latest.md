# Independent Audit - 2026-05-23T15:50:46Z

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

Sampled `HEAD`: `764326fc94ad` (`Refresh independent audit status`). Recent
history is still audit-only: the latest 20 sampled commits are all
`Refresh independent audit status` commits touching only `audits/latest.md`
and `progress.md`.

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json` and
`jq empty lanes/*/lane-status.json` both passed at this sample.

## Manifest/Status Snapshot

| Lane | Manifest denominator | Manifest mapped | Status PHP pass/fail | Status estimate | Commit/status field |
| --- | ---: | ---: | ---: | ---: | --- |
| difftastic | prose `603...` | 296 | 294 / 0 | 95% | pending in shared dirty worktree |
| dolt | prose `613...` | 567 | 308 / 0 | 95% | not committed |
| esbuild | prose `2,567...` | 251 | 251 / 0 | 77% | uncommitted |
| gitoxide | 2877 | 2021 | 3849 / 0 | 98% | pending |
| libsqlite | 1589 | 242 | 244 / 0 | 98% | uncommitted |
| lightningcss | 3532 | 1334 | 1507 / 0 | 86% | uncommitted; field names stale `HEAD 77590b16` |
| markerPDF | 288 | 236 | 358 / 0 | 95% | uncommitted |
| pandoc | prose `2276...` | 736 | 230 / 0 | 97% | pending |
| quadrable | prose `55...` | 55 | 151 / 0 | 99% | pending lane batch |
| rclone | 1601 | 522 | 522 / 0 | 98% | pending lane-local changes |
| readability | 1984 | 1804 | 162 / 0 | 98% | uncommitted |
| syncthing | 658 | 421 | 2986 / 0 | 98% | pending |

## Findings

1. **Critical - the repository is still not stable enough for an accepted aggregate root-test baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `progress.md:343`, `.tmux-team/prompts/*`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires capped supervised lane work,
     current owner/session tracking, small committed slices with passing tests,
     periodic repo-wide tests, and honest failure recording.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, and `progress.md:31` through
     `progress.md:42` still report every lane as `stopped`.
   - Evidence: process sampling found active workers anyway, including
     `run-tmux-agent` PIDs `2002880`, `2015494`, `2015497`, `2037826`,
     `2038067`, `2056865`, `2093333`, `2093373`, `2105518`, `2110713`,
     `2111071`, `2128917`, and `2129622`, plus dashboard/evaluator/capacity
     loops `2091655`, `2424048`, and `2452997`.
   - Evidence: broad Dolt BATS work was active during the audit, including
     PIDs `2010805`, `2010834`, `2010845`, `2010846`, `2010847`, `2144324`,
     and `2144657`.
   - Evidence: latest dirty samples reported `2067` default
     `git status --short` rows, `197` tracked dirty rows, `2109`
     all-untracked rows, and `197 files changed, 81422 insertions(+),
     7251 deletions(-)`.
   - Audit judgment: a no-argument `php tools/run-tests.php` result would not
     describe one accepted snapshot while writers and status publishers are
     active against this broad dirty tree.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and fail the dashboard contract.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:33`,
     `porting.html:36`, `porting.html:41` through `porting.html:50`,
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
     while sampled `HEAD` is `764326fc94ad`.
   - Evidence: published rows disagree with current manifests/status files:
     Difftastic is published as `160 / 417` mapped at `porting.html:54`, while
     current files report `296 / 603` in the manifest and `294` PHP passes in
     status; Dolt is published as `242 / 613` and `193 pass` at
     `porting.html:55`, while current files report `567 / 613` and `308 pass`;
     markerPDF is published as `159 / 78` at `porting.html:60`, while current
     files report `236 / 288`; rclone is published as `291 / 327` at
     `porting.html:63`, while current files report `522 / 1601`; Syncthing is
     published as `235 / 658` at `porting.html:65`, while current files report
     `421 / 658`.
   - Evidence: `porting.html:41` through `porting.html:50` still combine
     benchmark source with denominator and PHP pass/fail with mapped tests, so
     the dashboard does not expose the separate columns required by the goal.

3. **High - `progress.md` no longer describes the active system.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `progress.md:343`, `lanes/*/lane-status.json:4`,
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include
     active lanes, current owner/session, next task per lane, percentage
     estimates, latest commit, and blockers.
   - Evidence: the active-lane table still shows stale stopped sessions and
     old estimates from `5%` to `66%`, while current lane status estimates are
     `77%` to `99%` and active processes show many workers running.
   - Evidence: `progress.md:343` already records the same stale-dashboard,
     active-writer, non-normalized-count, and pending-dirty-batch blocker from
     the prior audit, but the active-lane table and dashboard remain
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
   - Evidence: lane `latestCommit` fields say `pending`, `not committed`,
     `uncommitted`, `pending lane batch`, or dirty-worktree prose across all
     lanes. LightningCSS still names `HEAD 77590b16` even though sampled
     repository `HEAD` is `764326fc94ad`.
   - Evidence: the latest 20 sampled commits are audit-only refresh commits;
     none integrate the current lane batches described by status files.

5. **High - near-complete percentages and "no blocker" language overstate upstream parity.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:4`,
     `lanes/libsqlite/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:4`,
     `lanes/quadrable/lane-status.json:4`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/readability/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:4`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of
     truth, passing tests are not enough, and hard features must be marked as
     blockers or future slices rather than silently skipped.
   - Evidence: nine lanes are marked `95%` to `99%`. Those estimates coexist
     with uncommitted lane work, stale dashboard output, pending root aggregate
     verification, skipped full upstream runners, and explicit future gaps such
     as Gitoxide full Cargo parity, markerPDF live Python/model workflows,
     rclone live-provider/mount parity, Pandoc full Haskell runner, Syncthing
     full upstream Go runner, and Quadrable full sync-fuzzer/benchmark runs.
   - Audit judgment: focused slices are useful, but these percentages read as
     near-native parity while the accepted-commit and root-test evidence does
     not support that claim.

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
   - Evidence: denominator `total` is sometimes numeric and sometimes prose
     mixing files, tests, fixtures, helper invocations, package paths, and
     benchmark entries.
   - Evidence: mapped units and PHP pass units are not comparable:
     Gitoxide maps `2021` but reports `3849` PHP passes; LightningCSS maps
     `1334` but reports `1507` PHP passes; Readability maps `1804` but reports
     only `162` PHP passes; Syncthing maps `421` but reports `2986` PHP
     passes.
   - Evidence: current Difftastic manifest/status files disagree internally:
     the manifest reports `603`/`296` at `UPSTREAM_TEST_MANIFEST.json:14`
     and `:15`, while `lane-status.json:5` still says `599` artifacts and
     `294` tests.

7. **Medium - evidence accounting still blends non-native, supplied, copied, live-provider-skipped, and plan-only artifacts with native parity.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:305`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:306`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:527`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:610`,
     `lanes/readability/lane-status.json:5`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:890`,
     `lanes/rclone/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` says generated fixtures, bridge
     calls, and shell-outs must not count as native implementation progress
     unless explicitly temporary oracle tooling.
   - Evidence: markerPDF counts supplied-document excerpts, package/lock
     metadata, workflow plans, helper scripts, Streamlit/Python/model planning,
     and `runnerStatus: not-executed`; Readability counts copied Mozilla
     fixture/oracle coverage while PHP behavior tests are a much smaller unit;
     rclone records live provider/mount parity as explicitly skipped while
     still claiming `98%`.
   - Audit judgment: the risk is not obvious shelling out in lane PHP; it is
     progress accounting that makes non-native evidence look too much like
     completed native parity.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

The exact duplicate-root gate returned no rows at the audit samples.
Post-commit handoff gates briefly matched focused PHP harness PIDs `2188950`
(`php tools/run-tests.php lanes/syncthing/tests lanes/libsqlite/tests`) and
`2204699` (`php tools/run-tests.php lanes/markerpdf/tests`), but both exited
before owner sampling. No root run was started because the stability gate
failed: active lane agents, watchdogs, status publishers,
evaluator/capacity/integrator loops, broad Dolt BATS work, uncommitted lane
batches, and stale generated dashboards persisted.

Validation commands run included:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json
jq empty lanes/*/lane-status.json
jq summary reads over every lanes/*/UPSTREAM_TEST_MANIFEST.json
jq summary reads over every lanes/*/lane-status.json
git log --oneline --name-only -n 20
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
