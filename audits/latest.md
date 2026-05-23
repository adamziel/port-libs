# Independent Audit - 2026-05-23T16:57:37Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, current worktree state, active
process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied fixture oracles,
shell-outs, live-provider anecdotes, and plan-only workflow artifacts are
treated as non-progress unless explicitly temporary oracle tooling.

Sampled `HEAD`: `f56bd1683c5d` (`Refresh independent audit status`). The latest
42 commits before the nearest recent implementation commit are audit-only
`Refresh independent audit status` commits.

`jq empty` passed for every manifest, every lane-status file, and
`porting-summary.json`. The required duplicate-root gate
`pgrep -af '^php tools/run-tests\.php( |$)'` returned no rows at this sample,
but the tree was not stable enough for a trustworthy no-argument root run.
Final pre-commit gates later matched transient focused PHP harness PIDs
`2706762`, `2706851`, and `2706966`; each exited before owner sampling.

## Manifest/Status Snapshot

| Lane | Current manifest denominator | Manifest mapped | Status PHP pass/fail | Status estimate | Commit/status field |
| --- | ---: | ---: | ---: | ---: | --- |
| difftastic | 614 inspected behavior artifacts | 308 | 306 / 0 | 96% | pending in shared dirty worktree |
| dolt | 613 executable test files | 591 | 316 / 0 | 95% | not committed |
| esbuild | 2,567 upstream entry points | 263 | 263 / 0 | 77% | uncommitted |
| gitoxide | 2,877 upstream files | 2,169 | 4,069 / 0 | 98% | pending |
| libsqlite | 1,589 | 251 | 251 / 0 | 98% | uncommitted |
| lightningcss | 3,532 behavior checks | 1,430 | 1,620 / 0 | 89% | uncommitted |
| markerPDF | 293 static behavior/reference units | 241 | 369 / 0 | 96% | uncommitted |
| pandoc | 2,276 inspected artifacts | 793 | 238 / 0 | 98% | pending |
| quadrable | 55 tracked paths plus runner notes | 55 | 163 / 0 | 99% | pending lane batch |
| rclone | 1,601 Go test/benchmark/example units | 540 | 540 / 0 | 98% | pending lane-local changes |
| readability | 1,984 Mocha tests | 1,885 | 169 / 0 | 98% | uncommitted; references old `532bf35f` |
| syncthing | 658 Go entry points | 452 | 3,151 / 0 | 98% | pending |

## Findings

1. **Critical - there is still no stable integration snapshot suitable for an accepted root-test baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `.tmux-team/prompts/*`, `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires capped supervised lane work,
     current owner/session tracking, small committed slices with passing tests,
     periodic repo-wide tests, and honest failure recording.
   - Evidence: `progress.md:25` still documents a target of two
     implementation lanes plus one auditor, and `progress.md:31` through
     `progress.md:42` reports every lane as `stopped`.
   - Evidence: active process sampling found 19 repo worker/status processes,
     including the dashboard updater, team watchdog, evaluator, capacity
     controller, Dolt runner, integrator, auditor, and all 12 lane agents.
   - Evidence: the worktree is not quiescent. Latest samples reported `217`
     tracked dirty rows, `2,346` total status rows including untracked files,
     and `217 files changed, 93953 insertions(+), 7384 deletions(-)`.
   - Evidence: manifest/status values changed while this audit was reading
     them. Difftastic moved from `613/306` to `614/308`, and LightningCSS moved
     from `1418` mapped units to `1430` mapped units during the audit sample
     window.
   - Evidence: the exact duplicate-root gate returned no rows, but a root run
     over this moving snapshot would not describe one accepted integration
     state.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and fail the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:65`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `porting-summary.json:15` through `porting-summary.json:212`.
   - Goal requirement at risk: `goal.md` requires a generated dashboard with
     average progress and per-lane columns for library, suite progress,
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32` and `porting-summary.json:2` still say
     generated `2026-05-23 04:57:16 UTC`; `porting.html:33`,
     `porting.html:36`, and `porting-summary.json:3` still identify source
     snapshot `bda83c6b93d4`, while sampled `HEAD` is `f56bd1683c5d`.
   - Evidence: published rows disagree with current manifests/status files:
     Difftastic is published as `160 / 417` but current is `308 / 614`; Dolt
     `242 / 613` vs `591 / 613`; esbuild `164 / 2567` vs `263 / 2567`;
     Gitoxide `1432 / 2877` vs `2169 / 2877`; libsqlite `149 / 1454` vs
     `251 / 1589`; LightningCSS `773 / 3532` vs `1430 / 3532`; markerPDF
     `159 / 78` vs `241 / 293`; Pandoc `426 / 2028` vs `793 / 2276`; rclone
     `291 / 327` vs `540 / 1601`; Readability `1031 / 1984` vs
     `1885 / 1984`; Syncthing `235 / 658` vs `452 / 658`.
   - Evidence: `porting.html:41` through `porting.html:50` still omits
     separate upstream-denominator and PHP pass/fail columns; `porting.html:54`
     through `porting.html:65` mixes PHP pass/fail with mapped coverage in one
     cell.

3. **High - `progress.md` does not describe the active system.**
   - Paths: `progress.md:14`, `progress.md:25`, `progress.md:31` through
     `progress.md:42`, `lanes/*/lane-status.json:4`,
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include
     active lanes, current owner/session, next task per lane, percentage
     estimates, latest commit, and blockers.
   - Evidence: the active-lane table still shows all sessions as `stopped`
     with estimates from `5%` to `66%`, while current lane status files report
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
   - Evidence: every lane's latest-commit field says `pending`,
     `not committed`, `uncommitted`, or dirty-worktree prose. Readability still
     references observed `HEAD 532bf35f`, while sampled `HEAD` is
     `f56bd1683c5d`.
   - Evidence: the latest 42 commits are audit-only refresh commits before the
     nearest recent implementation commit, while lane-status files describe
     newer uncommitted work.

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
   - Audit judgment: focused native slices are useful, but the percentages
     read as near-native parity while accepted-commit and root-test evidence
     does not support that claim.

6. **High - manifest/status count schemas are still not normalized or internally consistent.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/difftastic/lane-status.json:5` through
     `lanes/difftastic/lane-status.json:7`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:642`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:648`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:653`,
     `lanes/readability/lane-status.json:5` through
     `lanes/readability/lane-status.json:7`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
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
     `169` PHP passes; markerPDF maps `241` but reports `369` PHP passes.
   - Evidence: Difftastic's manifest reports `614` total and `308` mapped at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14` through `:15`, while
     its lane status still says `613` artifacts and `306` focused PHP tests at
     `lanes/difftastic/lane-status.json:5` through `:7`.
   - Evidence: Readability contains two contradictory warning summaries:
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:642` says `169` behavior
     tests and `2330` assertions after the Breitbart slice, while
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:653` still says `165`
     behavior tests and `2268` assertions after the older Folha slice.
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:648` separately records
     `phpBehaviorTests: 168`, while `lanes/readability/lane-status.json:6`
     reports `phpPass: 169`.

7. **Medium - progress accounting still blends non-native, copied, live-provider-skipped, and plan-only artifacts with native parity.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:313`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:642`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` says generated fixtures, bridge
     calls, and shell-outs must not count as native implementation progress
     unless explicitly temporary oracle tooling.
   - Evidence: markerPDF's status string includes numerous `plan` boundaries,
     while `runnerStatus` is still `not-executed` and the lane status leaves
     live Python/model/Streamlit/FastAPI/multiprocessing workflows unexecuted.
   - Evidence: Readability count growth is dominated by copied Mozilla
     fixture/oracle coverage: 113 copied fixture pages and 1,885 mapped checks,
     while native PHP behavior tests are 169. rclone explicitly leaves live
     provider/mount parity open while reporting `98%`.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

The gate returned no rows at the main audit sample. Final pre-commit gates later
matched transient focused lane harnesses:

```text
2706762 php tools/run-tests.php lanes/readability/tests
2706851 php tools/run-tests.php lanes/quadrable/tests/TransportKeyHashTest.php lanes/quadrable/tests/SyncTest.php
2706966 php tools/run-tests.php lanes/quadrable/tests
```

Each process exited before `ps` could collect owner evidence, and a follow-up
sample returned no rows. No root run was appropriate because the tree was not
stable enough: active lane agents, watchdog/status publishers,
evaluator/capacity/integrator jobs, pending dirty lane batches, audit-only
recent history, changing manifest/status counts, transient focused PHP
harnesses, and stale generated dashboards persisted.

Validation commands run included:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
jq summary reads over every lanes/*/UPSTREAM_TEST_MANIFEST.json
jq summary reads over every lanes/*/lane-status.json
sed/nl reads of goal.md, progress.md, porting.html, porting-summary.json, audits/latest.md
git log --oneline --decorate -n 20
git log --format='%h %s' -n 60
git show --stat --oneline --decorate -n 5
git status --short --untracked-files=no
git status --short --untracked-files=all
git diff --shortstat
git rev-parse --short=12 HEAD
pgrep -af '^php tools/run-tests\.php( |$)'
pgrep -af 'scripts/run-tmux-agent|scripts/run-dashboard-updater-loop|scripts/run-evaluator-loop|scripts/run-team-watchdog|scripts/run-capacity-controller-loop|scripts/run-integrator-loop|scripts/run-artifact|php tools/run-tests\.php|bats|testrunner\.tcl|go test|cargo test'
```

## Next Intervention

Freeze active writers/status publishers and duplicate focused/root PHP loops,
then accept or reject dirty lane batches one lane at a time. From that frozen
tree, normalize manifest/status denominator, mapped, PHP pass/fail, runner,
blocker, and commit fields; regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the same accepted snapshot;
rerun the exact duplicate-root gate; and capture one quiesced root
`php tools/run-tests.php` run only if the gate remains empty.
