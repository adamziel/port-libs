# Independent Audit - 2026-05-23T15:43:24Z

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

Sampled `HEAD`: `77590b16c662` (`Refresh independent audit status`). Recent
history is still audit-only: the latest 20 sampled commits are all `Refresh
independent audit status` commits touching only `audits/latest.md` and
`progress.md`.

Manifest/status snapshot from this audit sample:

| Lane | Manifest denominator | Manifest mapped | Status PHP pass/fail | Status estimate | Commit/status field |
| --- | ---: | ---: | ---: | ---: | --- |
| difftastic | prose `599 inspected...` | 294 | 294 / 0 | 95% | pending in shared dirty worktree |
| dolt | prose `613 upstream...` | 567 | 305 / 0 | 95% | not committed |
| esbuild | prose `2,567 counted...` | 247 | 247 / 0 | 76% | uncommitted |
| gitoxide | 2877 | 2021 | 3849 / 0 | 98% | pending |
| libsqlite | 1589 | 242 | 242 / 0 | 98% | uncommitted |
| lightningcss | 3532 | 1328 | 1500 / 0 | 86% | uncommitted; field still names `HEAD 2898be34` |
| markerPDF | 287 | 235 | 358 / 0 | 95% | uncommitted |
| pandoc | prose `2276 upstream...` | 728 | 229 / 0 | 97% | pending |
| quadrable | prose `55 tracked...` | 55 | 150 / 0 | 99% | pending lane batch |
| rclone | 1601 | 520 | 520 / 0 | 98% | pending lane-local changes |
| readability | 1984 | 1789 | 161 / 0 | 98% | uncommitted |
| syncthing | 658 | 413 | 2961 / 0 | 98% | pending |

## Findings

1. **Critical - the repository is still not stable enough for an accepted aggregate root-test baseline.**
   - Paths: `progress.md:25`, `progress.md:31`, `progress.md:42`,
     `.tmux-team/prompts/*`, `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires capped supervised lane work,
     current owner/session tracking, small committed slices with passing tests,
     periodic repo-wide tests, and honest failure recording.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, and `progress.md:31` through
     `progress.md:42` still report every lane as `stopped`.
   - Evidence: process sampling found active lane/status automation anyway:
     `run-tmux-agent` PIDs `1985770`, `1992015`, `1996241`, `1996320`,
     `1999022`, `2000310`, `2002838`, `2002880`, `2003991`, `2005394`,
     `2007720`, `2009533`, `2009571`, `2009651`, and `2010092`, plus
     watchdog/evaluator/capacity/dashboard loops `2347911`, `2424048`,
     `2452997`, and `2479222`.
   - Evidence: broad upstream work was also active during the audit, including
     Dolt BATS PIDs `2010805`, `2010834`, `2010845`, `2010846`, `2010847`,
     `2011725`, and `2012095`.
   - Evidence: latest dirty samples reported `2043` default
     `git status --short` rows, `197` tracked dirty rows, `2085` all-untracked
     rows, and `197 files changed, 80131 insertions(+), 7254 deletions(-)`.
   - Audit judgment: a no-argument `php tools/run-tests.php` result would not
     describe one accepted snapshot while writers and status publishers are
     active against this broad dirty tree.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and fail the dashboard contract.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:33`,
     `porting.html:36`, `porting.html:54` through `porting.html:65`,
     `porting-summary.json:2` through `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md` requires a generated dashboard with
     average progress and per-lane columns for library, suite progress,
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32` and `porting-summary.json:2` still publish
     `2026-05-23 04:57:16 UTC`; `porting.html:33`,
     `porting.html:36`, `porting-summary.json:3`, and
     `porting-summary.json:5` still identify snapshot `bda83c6b93d4`, while
     sampled `HEAD` is `77590b16c662`.
   - Evidence: published rows disagree with current manifests/status files:
     Difftastic is published as `160 / 417` mapped at `porting.html:54`, while
     current files report `294 / 599`; Dolt is published as `242 / 613` and
     `193 pass` at `porting.html:55`, while current files report `567 / 613`
     and `305 pass`; markerPDF is published as `159 / 78` mapped at
     `porting.html:60`, while current files report `235 / 287`; rclone is
     published as `291 / 327` at `porting.html:63`, while current files report
     `520 / 1601`; Syncthing is published as `235 / 658` at
     `porting.html:65`, while current files report `413 / 658`.
   - Evidence: the HTML still combines benchmark source/denominator and
     mapped/PHP pass/fail into compact cells, so it does not expose the
     separate audit columns required by the goal.

3. **High - `progress.md` no longer describes the active system.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `progress.md:342`, `lanes/*/lane-status.json:4`,
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include
     active lanes, current owner/session, next task per lane, percentage
     estimates, latest commit, and blockers.
   - Evidence: the active-lane table still shows stale stopped sessions and
     old estimates from `5%` to `66%`, while current lane-status estimates are
     `76%` to `99%`.
   - Evidence: `progress.md:342` already records the same stale-dashboard,
     active-writer, and non-normalized-count blocker from the prior audit, but
     the active-lane table and dashboard still have not been corrected.

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
     lanes. LightningCSS names `HEAD 2898be34` even though sampled repository
     `HEAD` is `77590b16c662`.
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
   - Evidence: nine lanes are now marked `95%` to `99%`. Those estimates
     coexist with uncommitted lane work, stale dashboard output, pending root
     aggregate verification, skipped full upstream runners, and explicit
     future gaps such as Gitoxide full Cargo parity, markerPDF live Python/model
     workflows, rclone live-provider/mount parity, Pandoc full Haskell runner,
     and Syncthing full upstream Go runner.
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
     `1328` but reports `1500` PHP passes; Readability maps `1789` but reports
     only `161` PHP passes; Syncthing maps `413` but reports `2961` PHP
     passes.

7. **Medium - evidence accounting still blends non-native, supplied, copied, live-provider-skipped, and plan-only artifacts with native parity.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:303`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/lane-status.json:6`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`,
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

The exact duplicate-root gate returned no rows at the main audit samples. A
post-edit validation sample briefly returned focused lane PID `2058746`
(`php tools/run-tests.php lanes/quadrable/tests`), which exited before owner
sampling. Later handoff samples returned root PID `2065489`, which exited
before owner sampling, then root PID `2076405`, with owner evidence
`2076405 claude 2033369 00:06 Rs php tools/run-tests.php`. No duplicate root
run was started because the stability gate failed: active lane agents,
watchdogs, status publishers, evaluator/capacity/integrator loops, broad Dolt
BATS work, uncommitted lane batches, and stale generated dashboards persisted.

Validation commands run included:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json
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
