# Independent Audit - 2026-05-23T20:39:13Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, worktree state, process state,
and the required root-harness duplicate gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

`HEAD` was sampled at `d04064563009`. The latest sampled worktree had `3477`
`git status --short --untracked-files=all` rows, `254` tracked dirty rows, and
`254 files changed, 100887 insertions(+), 12808 deletions(-)`.

The required exact root-harness gate was checked before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

An early sample matched active PHP harnesses:

```text
573130 php tools/run-tests.php lanes/syncthing/tests
580285 php tools/run-tests.php lanes/syncthing/tests
582555 php tools/run-tests.php lanes/syncthing/tests
584410 php tools/run-tests.php lanes/difftastic/tests lanes/esbuild/tests lanes/libsqlite/tests lanes/lightningcss/tests lanes/pandoc/tests lanes/quadrable/tests lanes/readability/tests
584449 php tools/run-tests.php lanes/syncthing/tests
```

Owner evidence before some processes exited:

```text
580285 claude 580128 01:08 R+ php tools/run-tests.php lanes/syncthing/tests
582555 claude 582434 00:50 R+ php tools/run-tests.php lanes/syncthing/tests
584449 claude 584345 00:33 R+ php tools/run-tests.php lanes/syncthing/tests
```

A later exact gate returned no rows, but I still did not start
`php tools/run-tests.php` because the tree was not stable enough: active lane
agents, capacity jobs, status/control loops, and a broad Dolt BATS runner were
present, and the worktree was a large moving aggregate.

Process sampling showed active writers/status/control loops despite
`progress.md:25` documenting a two-lane-plus-auditor target and
`progress.md:31` through `progress.md:42` reporting all lanes stopped.
`tmux ls` reported `95` sessions. Recent Git history is still audit/status
dominated: `HEAD` is `106` commits after implementation commit `b75226d1`, and
the latest 25 sampled commits are `Refresh independent audit status` or
`Record integration hold status`.

Current manifest/status sample versus the published dashboard:

| Lane | Current manifest mapped / denominator | Current lane status | Published dashboard mapped / denominator |
| --- | ---: | ---: | ---: |
| difftastic | 348 / prose `696` artifacts | 348 pass, 99% | 160 / 417 |
| dolt | 610 / prose latest-runner total | 332 pass cases, 95% | 242 / 613 |
| esbuild | 288 / prose `2567` tests | 288 pass, 86% | 164 / 2567 |
| gitoxide | 2690 / 2877 | 5322 assertions, 98% | 1432 / 2877 |
| libsqlite | 272 / 1589 | 271 pass, 98% | 149 / 1454 |
| LightningCSS | 1705 / 3532 | 2094 assertions, 93% | 773 / 3532 |
| markerPDF | 263 / 314 static units | 398 pass, 98% | 159 / 78 |
| pandoc | 912 / prose `2276` artifacts | 258 pass, 98% | 426 / 2028 |
| quadrable | 55 / prose `55` paths plus scenarios | 177 pass, 99% | 55 / 55 |
| rclone | 640 / 1601 | 640 pass, 99% | 291 / 327 |
| readability | 1984 / 1984 upstream Mocha tests | 187 pass, 99% | 1031 / 1984 |
| syncthing | 525 / 658 | 3843 assertions, 99% | 235 / 658 |

## Findings

1. **Critical - active writers and broad test/control loops still block a
   trustworthy aggregate baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-capacity-executor-queue.sh`,
     `.tmux-team/tmp/port-*.md`, and `.tmux-team/logs/*`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable tested commits, integration cleanup, and honest repo-wide
     verification.
   - Evidence: sampled process state showed primary lane agents, capacity
     executor/feeder jobs, dashboard/evaluator/watchdog/integrator/auditor
     loops, and a broad Dolt BATS run. The dirty tree reached `254` tracked
     dirty rows and over `100k` inserted lines. A root result from this state
     would not be an accepted checkpoint.

2. **Critical - the public coordination surface is stale and contradicts the
   current manifests/status files.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`, and
     `porting-summary.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current tracking, a visible
     dashboard, and per-lane denominator/mapped/PHP/status/commit fields.
   - Evidence: `porting.html` still publishes generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while
     sampled `HEAD` is `d04064563009`. Dashboard rows disagree with current
     manifests for nearly every lane, including rclone `291 / 327` versus
     current `640 / 1601`, markerPDF `159 / 78` versus current `263 / 314`,
     and Syncthing `235 / 658` versus current `525 / 658` plus `3843` PHP
     assertions.

3. **High - near-complete lane percentages are not backed by accepted
   implementation commits or aggregate root verification.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:4`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:4`,
     `lanes/readability/lane-status.json:13`, and parallel status fields in
     the other lane-status files.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`,
     `goal.md:36`, and `goal.md:48` require small correct slices, committed
     handoffs, passing tests, and integration cleanup before more work is
     assigned.
   - Evidence: lane statuses now claim 95-99% progress for most lanes, but
     all sampled `latestCommit` fields are pending, uncommitted, or dirty-batch
     prose. The latest 106 commits after `b75226d1` are audit/status/integration
     hold work in the sampled history, not accepted lane implementation commits.

4. **High - manifest denominator and PHP-count schemas are still
   non-normalized, so percentages are not comparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2208`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through `:15`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14` through `:15`, and
     `lanes/*/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as source of truth, and comparable dashboard
     fields.
   - Evidence: `benchmarkDenominator.total` mixes integers, prose artifact
     inventories, path inventories, and a Dolt latest-runner paragraph. PHP
     counts mix behavior tests, assertions, and PASS cases: Gitoxide reports
     `5322` assertions, Syncthing reports `3843` assertions, Dolt reports
     `332` PASS cases, and Readability reports `187` native PHP tests while
     the manifest maps `1984 / 1984` upstream Mocha checks.

5. **High - markerPDF and Readability overstate native parity from static,
   supplied, copied, or upstream-oracle evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through `:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:359`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:602`,
     `lanes/markerpdf/lane-status.json:4` through `:13`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through `:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:40` through `:58`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:723`, and
     `lanes/readability/lane-status.json:4` through `:13`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native standard-PHP ports,
     bridge/generated/shell-out non-progress handling, meaningful fixture
     parity, and explicit hard-feature gaps.
   - Evidence: markerPDF is at `98%` while its runner status is
     `not-executed`, the upstream has `0` committed Python tests, and the
     manifest explicitly relies on static units, archive pairs, surrogates,
     supplied-document excerpts, and plan-only boundaries while avoiding the
     Python/PDF/model stack. Readability maps `1984 / 1984` upstream Mocha
     checks and copied all 130 Mozilla fixtures, but native PHP status is
     `187` focused tests; upstream JavaScript oracle success is valuable
     evidence, not full native PHP parity.

6. **Medium - blocker fields still mix slice-local green checks with full-port
   blockers.**
   - Paths: `lanes/difftastic/lane-status.json:12`,
     `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers, parity beyond passing local tests,
     and explicit hard-feature gaps.
   - Evidence: blocker strings begin with "No ... blocker" while the same
     fields disclose pending root verification, unexecuted full upstream
     runners, excluded live-provider/model/server paths, or broad remaining
     parity gaps. Slice-local status should be separated from full-port
     blockers.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact gate initially matched active PHP harnesses and owner
evidence confirmed several `claude` processes. A later gate returned no rows,
but the tree remained unstable because active writer/status/control loops and
broad upstream BATS work persisted. Running a no-argument root harness here
would not satisfy the goal's stable integration requirement.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
exact duplicate-root gate remains empty.
