# Independent Audit - 2026-05-23T18:03:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
lane status files, recent Git history, current worktree state, and process
state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

Sampled `HEAD`: `1aa973695e98` (`Refresh independent audit status`). The latest
30 sampled commits are all audit-only `Refresh independent audit status`
commits; the nearest recent implementation commit remains `b75226d1` (`Port
rclone OneDrive Object.Update upload selection`) after 53 newer audit refresh
commits.

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
porting-summary.json` passed at this sample.

## Current Snapshot

The tree is not quiescent. Latest samples reported `2576`
`git status --short --untracked-files=all` rows, `225` tracked dirty rows, and
`225 files changed, 104278 insertions(+), 8934 deletions(-)`.

The required duplicate-root gate was positive, so I did not run
`php tools/run-tests.php`:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
2951461 php tools/run-tests.php
```

Owner evidence for the initial active root:

```text
2951461 claude 2951384 67 R+ php tools/run-tests.php
```

A later handoff gate showed a new focused lane harness and a new exact root
harness:

```text
2988003 php tools/run-tests.php lanes/syncthing/tests
3065293 php tools/run-tests.php
```

Owner evidence:

```text
2988003 claude 2943732 46 Rs php tools/run-tests.php lanes/syncthing/tests
3065293 claude 2972140 5 Rs php tools/run-tests.php
```

Process sampling also found `84` matching repo worker/status/test processes,
including dashboard, watchdog, evaluator, capacity-controller, integrator,
auditor, Dolt/SQLite upstream runners, lane agents, focused PHP harnesses, and
no-argument root PHP harnesses while `progress.md` still reports every lane as
`stopped`.

| Lane | Current manifest mapped / denominator | Published dashboard mapped / denominator |
| --- | ---: | ---: |
| difftastic | 318 / 620 | 160 / 417 |
| dolt | 598 / 613 embedded in prose | 242 / 613 |
| esbuild | 270 / 2,567 | 164 / 2,567 |
| gitoxide | 2,179 / 2,877 | 1,432 / 2,877 |
| libsqlite | 258 / 1,589 | 149 / 1,454 |
| lightningcss | 1,476 / 3,532 | 773 / 3,532 |
| markerPDF | 247 / 299 | 159 / 78 |
| pandoc | 817 / 2,276 | 426 / 2,028 |
| quadrable | 55 / 55 | 55 / 55 |
| rclone | 572 / 1,601 | 291 / 327 |
| readability | 1,971 / 1,984 | 1,031 / 1,984 |
| syncthing | 468 / 658 | 235 / 658 |

## Findings

1. **Critical - duplicate root harnesses and active writers prevent a stable
   integration baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-php-dirty-root.sh`, and `lanes/*/lane-status.json:12` through
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:20` requires capped supervised
     parallelism; `goal.md:29`, `goal.md:48`, `goal.md:49`, and `goal.md:52`
     require committed, verified, visible progress and periodic repo-wide tests.
   - Evidence: the required duplicate-root gate found active no-argument root
     harness PID `2951461`, then later active root PID `3065293`, both owned by
     `claude`. Active process sampling found `84` repo worker/status/test
     processes.
   - Impact: any new root run would be a duplicate, and existing root results
     cannot be accepted as the stable baseline while active writers continue to
     mutate manifests, statuses, generated dashboards, and lane files.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and do
   not satisfy the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:65`,
     `porting-summary.json:2` through `porting-summary.json:145`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with average progress, benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: `porting.html:32` and `porting-summary.json:2` still say
     generated `2026-05-23 04:57:16 UTC`; `porting.html:33` and
     `porting-summary.json:3` through `porting-summary.json:5` still publish
     source snapshot `bda83c6b93d4`, while sampled `HEAD` is `1aa973695e98`.
   - Evidence: current manifests disagree with the dashboard for every mapped
     count except Quadrable. Severe examples: markerPDF is `247 / 299` current
     versus `159 / 78` published, rclone is `572 / 1601` versus `291 / 327`,
     Readability is `1971 / 1984` versus `1031 / 1984`, and LightningCSS is
     `1476 / 3532` versus `773 / 3532`.
   - Evidence: `porting.html:41` through `porting.html:50` still lacks separate
     upstream-denominator and PHP pass/fail columns; rows such as
     `porting.html:54` through `porting.html:65` mix PHP pass/fail and mapped
     coverage in one cell.

3. **High - `progress.md` contradicts the live supervision state.**
   - Paths: `progress.md:14`, `progress.md:25`, and `progress.md:31` through
     `progress.md:42`.
   - Goal requirement at risk: `goal.md:20` and `goal.md:44` require accurate
     active lanes, owners/sessions, blockers, latest commit, next task, and
     percentage estimates.
   - Evidence: `progress.md:25` documents a launch target of two implementation
     lanes plus one auditor, but process sampling found all primary lane agents
     plus dashboard/evaluator/watchdog/capacity/integrator/auditor/Dolt/SQLite
     runner activity.
   - Evidence: the Active Lanes table still reports all sessions as `stopped`
     with stale estimates such as Gitoxide `66%`, LightningCSS `14%`,
     markerPDF `10%`, libsqlite `12%`, and Dolt `5%`, while current
     `lanes/*/lane-status.json:4` files report many lanes at `95` to `99`.
   - Evidence: `progress.md:14` leaves the independent auditor loop unchecked
     despite repeated audit-refresh commits and active auditor processes.

4. **High - dirty lane batches are being described as progress before they are
   accepted.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`, `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`, `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`, `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small,
     reviewable slices with passing tests, commits, progress updates, and
     integration.
   - Evidence: sampled `latestCommit` fields say `pending`, `uncommitted`, `not
     committed`, or dirty-worktree prose across all lanes. Recent Git history is
     now 53 audit-refresh commits past the nearest recent implementation commit.
   - Impact: focused lane tests and upstream probes may be useful evidence, but
     they cannot count as accepted implementation progress until each batch is
     isolated, verified from a stable tree, committed, and reflected in the
     dashboard/status files from the same snapshot.

5. **High - manifest and status schemas are still not comparable across lanes.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:1990`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:672` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:683`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1173`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:45`
     require real upstream denominators, mapped upstream tests, PHP pass/fail
     counts, and comparable dashboard fields.
   - Evidence: Dolt's `benchmarkDenominator.total` is a long mutable runner log
     with `613` embedded in prose while other lanes use numeric totals.
   - Evidence: LightningCSS reports `1476 / 3532` at the top but its warning
     still says native PHP maps `1,438` checks; Syncthing reports `468 / 658`
     at the top but its warning still says `462`; Readability reports
     `1971 / 1984` mapped while its warning/native field still says `172`
     local behavior tests and `2382` assertions, and lane status now reports
     `175` PHP passes.
   - Impact: portfolio averages and "near complete" claims are mixing upstream
     files, entry points, behavior checks, local assertions, PHP test cases, and
     prose logs.

6. **High - near-complete percentages understate remaining full-parity gaps.**
   - Paths: `lanes/difftastic/lane-status.json:4` through
     `lanes/difftastic/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:4` through
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:4` through
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:4` through
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:4` through
     `lanes/pandoc/lane-status.json:12`,
     `lanes/quadrable/lane-status.json:4` through
     `lanes/quadrable/lane-status.json:12`,
     `lanes/rclone/lane-status.json:4` through
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:4` through
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and `goal.md:40`
     require precise blockers and hard features to be recorded as blockers or
     future slices.
   - Evidence: many lanes now claim `95` to `99` estimated progress while root
     integration remains pending, current work is uncommitted, and full upstream
     runners or full provider/model/integration suites remain explicitly
     unexecuted.
   - Audit judgment: "no blocker" is only valid for the focused slice. The
     coordination data needs separate slice-local blockers and full-parity
     blockers.

7. **Medium - static inventories, bounded oracle evidence, and copied/generated
   fixture work are still overweighted as native progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:329`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:559`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:960`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:672`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:573`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1173`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, and `goal.md:37`
     say generated fixtures, bridge calls, shell-outs, static inventories, and
     shallow fixture parity are not native implementation progress by
     themselves.
   - Evidence: markerPDF runner status remains `not-executed`; rclone and
     Syncthing rely on bounded/static runner evidence rather than full
     provider/protocol suite parity; Readability maps near-full upstream fixture
     inventory while the native PHP behavior count is still a small local subset.

## Test Gate

I did not run `php tools/run-tests.php`.

Required gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

The exact gate returned active no-argument root harnesses during the audit
(`2951461`, later `3065293`), and owner evidence confirmed both were owned by
`claude`. Starting another root harness would violate the duplicate-root
constraint.

Validation run during this audit:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
```

## Next Intervention

Freeze active lane agents, dashboard/evaluator/capacity/watchdog/auditor loops,
duplicate PHP harnesses, Dolt BATS shards, and SQLite TCL runners. Validate
manifests from the frozen tree, enforce atomic writes for manifest/status and
dashboard files, accept or reject dirty lane batches one lane at a time,
normalize denominator/mapped/PHP pass-fail/runner/commit fields, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
one accepted snapshot, then run exactly one quiesced root
`php tools/run-tests.php` only after the exact duplicate-root gate remains
empty.
