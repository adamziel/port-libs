# Independent Audit - 2026-05-23T16:31:11Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, current worktree state, active
process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, supplied fixtures, shell-outs,
live-provider anecdotes, and plan-only workflow artifacts are treated as
non-progress unless explicitly temporary oracle tooling.

Sampled `HEAD`: `d5ccd4b15f36` (`Refresh independent audit status`). Recent
history remains audit-only: the latest 12 sampled commits touch only
`audits/latest.md` and `progress.md`.

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
porting-summary.json` passed at this sample.

## Manifest/Status Snapshot

| Lane | Manifest denominator | Manifest mapped | Status PHP pass/fail | Status estimate | Commit/status field |
| --- | ---: | ---: | ---: | ---: | --- |
| difftastic | 610 inspected artifacts | 303 | 303 / 0 | 96% | pending in shared dirty worktree |
| dolt | 613 upstream executable test files | 582 | 312 / 0 | 95% | not committed |
| esbuild | 2,567 upstream test entry points | 258 | 258 / 0 | 77% | uncommitted |
| gitoxide | 2,877 | 2,138 | 3,983 / 0 | 98% | pending |
| libsqlite | 1,589 | 248 | 248 / 0 | 98% | uncommitted |
| lightningcss | 3,532 | 1,413 | 1,589 / 0 | 87% | uncommitted; says `HEAD 3a8f4f26` |
| markerPDF | 291 static behavior/reference units | 239 | 365 / 0 | 96% | uncommitted |
| pandoc | 2,276 inspected artifacts | 774 | 235 / 0 | 98% | pending |
| quadrable | 55 tracked upstream paths plus prose runner notes | 55 | 155 / 0 | 99% | pending lane batch |
| rclone | 1,601 static Go test/benchmark/example units | 535 | 535 / 0 | 98% | pending lane-local changes |
| readability | 1,984 | 1,853 | 167 / 0 | 98% | uncommitted |
| syncthing | 658 | 443 | 3,081 / 0 | 98% | pending |

## Findings

1. **Critical - there is still no stable integration snapshot suitable for an accepted root-test baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `progress.md:350` through `progress.md:351`,
     `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`, `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`, `.tmux-team/prompts/*`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires capped supervised lane work,
     current owner/session tracking, small committed slices with passing tests,
     periodic repo-wide tests, and honest failure recording.
   - Evidence: `progress.md:25` still documents a target of two
     implementation lanes plus one auditor, and `progress.md:31` through
     `progress.md:42` reports every lane as `stopped`.
   - Evidence: process sampling found 55 matching repo process rows, including
     active lane agents for Difftastic, rclone, markerPDF, Syncthing,
     Dolt-runner, Gitoxide, esbuild, LightningCSS, Dolt, auditor, Quadrable,
     Readability, Pandoc, and integrator, plus dashboard updater PID `2222131`,
     team watchdog PID `2347911`, evaluator PID `2424048`, and capacity
     controller PID `2452997`.
   - Evidence: the worktree is not quiescent. Latest samples reported `2251`
     all-status rows, `208` tracked dirty rows, and `208 files changed, 89397
     insertions(+), 7286 deletions(-)`.
   - Evidence: the required exact duplicate-root gate initially returned no
     rows, but a later required gate found transient root PID `2328967`
     (`php tools/run-tests.php`), which exited before owner sampling. No
     duplicate root run was started.
   - Audit judgment: a new root run would not describe one accepted snapshot
     while agents, dashboard/status publishers, capacity jobs, root/focused
     test loops, and broad dirty batches are active.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and fail the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:65`,
     `porting-summary.json:2` through `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md` requires a generated dashboard with
     average progress and per-lane columns for library, suite progress,
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32` and `porting-summary.json:2` still say
     generated `2026-05-23 04:57:16 UTC`; `porting.html:33`,
     `porting.html:36`, and `porting-summary.json:3` still identify source
     snapshot `bda83c6b93d4`, while sampled `HEAD` is `d5ccd4b15f36`.
   - Evidence: published rows disagree with current manifests/status files.
     Difftastic is published as `160 / 417`, while current status is `303`
     PHP pass and the manifest is `303 / 610`; Dolt is `242 / 613`, while
     current is `582 / 613`; Gitoxide is `1432 / 2877`, while current is
     `2138 / 2877`; markerPDF is `159 / 78`, while current is `239 / 291`;
     Pandoc is `426 / 2028`, while current is `774 / 2276`; rclone is
     `291 / 327`, while current is `535 / 1601`; Syncthing is `235 / 658`,
     while current is `443 / 658`.
   - Evidence: `porting.html:41` through `porting.html:50` still combine
     benchmark source with denominator and PHP pass/fail with mapped tests, so
     upstream denominator and PHP pass/fail are not separately exposed as the
     goal requires.

3. **High - `progress.md` does not describe the active system.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `progress.md:350` through `progress.md:351`,
     `lanes/*/lane-status.json:4`,
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include
     active lanes, current owner/session, next task per lane, percentage
     estimates, latest commit, and blockers.
   - Evidence: the active-lane table still shows all sessions as `stopped`
     with estimates from `5%` to `66%`, while current lane statuses report
     estimates from `77%` to `99%` and process sampling shows many lane agents
     and publishers running.
   - Evidence: the coordination table still contains stale next tasks such as
     Gitoxide SOCKS/proxy work, LightningCSS CSSOM/grid work, and Dolt idle
     deferral, while current lane statuses describe different pending batches.

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
   - Evidence: every lane's latest-commit/status field says `pending`,
     `not committed`, `uncommitted`, or dirty-worktree prose. LightningCSS
     embeds stale `HEAD 3a8f4f26` while sampled `HEAD` is `d5ccd4b15f36`;
     Readability still references observed `HEAD a737c8b1`.
   - Evidence: the latest 12 sampled commits are audit-only refresh commits;
     none integrate the current lane batches described by status files.

5. **High - near-complete percentages and "no blocker" language overstate accepted upstream parity.**
   - Paths: `lanes/*/lane-status.json:4`,
     `lanes/*/lane-status.json:12`,
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
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator, mapped upstream tests, PHP passing/failing counts, and a
     dashboard whose rows can be compared across lanes.
   - Evidence: denominator `total` is numeric in some manifests and prose in
     others. The mapped unit differs by lane: inspected artifacts, executable
     test files, static behavior units, upstream test functions, copied fixture
     checks, or local behavior checks.
   - Evidence: PHP pass units are not comparable. Gitoxide maps `2138`
     upstream units but reports `3983` PHP passes; Syncthing maps `443` but
     reports `3081` PHP passes; Readability maps `1853` but reports only `167`
     PHP passes; markerPDF maps `239` but status reports `365` PHP passes.
   - Evidence: manifest/status counts disagree in places. markerPDF manifest
     records `phpBehaviorTests` as `366`, while lane status reports `365`;
     Syncthing manifest maps `443`, while lane status text still says `440`
     checks and its manifest warning still says `436` focused lane checks;
     Readability manifest warning still cites `165` local behavior tests while
     lane status reports `167`.

7. **Medium - progress accounting still blends non-native, copied, live-provider-skipped, and plan-only artifacts with native parity.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:239`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:626`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`.
   - Goal requirement at risk: `goal.md` says generated fixtures, bridge calls,
     and shell-outs must not count as native implementation progress unless
     explicitly temporary oracle tooling.
   - Evidence: markerPDF counts workflow plans, package/lock metadata,
     dependency graph evidence, benchmark/Nougat plan-only subprocess metadata,
     Streamlit/FastAPI planning, remote polling plans, and supplied-document
     excerpts while live Python/model execution remains blocked.
   - Evidence: Readability count growth is dominated by copied Mozilla
     fixture/oracle coverage: 111 copied fixture pages and 1853 mapped checks,
     while native PHP behavior tests are 167. rclone explicitly leaves live
     provider/mount parity open while reporting `98%`.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

The first gate returned no rows. A later gate returned transient root PID
`2328967` with command `php tools/run-tests.php`; the owner-sampling command
returned only the header because the process exited before owner sampling. No
duplicate root run was started. Even after the later exact gate
cleared, no root run was appropriate because the tree was not stable enough:
active lane agents, watchdog/status publishers, dashboard/evaluator/capacity
jobs, pending dirty lane batches, audit-only recent history, and stale generated
dashboards persisted.

Validation commands run included:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
jq summary reads over every lanes/*/UPSTREAM_TEST_MANIFEST.json
jq summary reads over every lanes/*/lane-status.json
sed, nl, and rg reads of goal.md, progress.md, porting.html, porting-summary.json, audits/latest.md
git log --oneline --decorate -n 15
git log --oneline --decorate --name-only -n 12
git show --stat --oneline --decorate --no-renames -n 8 HEAD
git status --short --untracked-files=no
git status --short --untracked-files=all
git diff --shortstat
git rev-parse --short=12 HEAD
pgrep -af '^php tools/run-tests\.php( |$)'
pgrep -af 'scripts/run-tmux-agent|scripts/run-dashboard-updater-loop|scripts/run-evaluator-loop|scripts/run-team-watchdog|scripts/run-capacity-controller-loop|php tools/run-tests\.php|codex|bats|go test|testrunner\.tcl|cargo test|npm test'
```

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops,
validate all manifests from the frozen tree, accept or reject dirty lane batches
one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, blocker, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced root `php tools/run-tests.php` run if the gate remains empty.
