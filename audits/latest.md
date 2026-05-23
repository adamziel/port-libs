# Independent Audit - 2026-05-23T20:28:57Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, worktree state, and process
state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, copied oracle
fixtures, and shell-outs are treated as non-progress unless explicitly
temporary oracle tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

Sampled `HEAD` moved during this audit from `e5e1cf1c56de` to
`7b6005d22e22`. Recent history after the nearest sampled implementation commit
`b75226d1` is now `102` commits, consisting of audit refresh and integration
hold status commits rather than accepted lane implementation commits.

The worktree is still not quiescent. Latest samples reported `3378`
`git status --short --untracked-files=all` rows, `248` tracked dirty rows, and
`248 files changed, 99515 insertions(+), 12688 deletions(-)`. Process sampling
reported `21` repo worker/status/test-control script matches and `95` tmux
sessions while `progress.md:25` documents a target of two implementation lanes
plus one auditor and `progress.md:31` through `progress.md:42` still mark every
lane as `stopped`.

The required exact root-harness gate:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

initially matched an active no-argument root harness and focused lane shards:

```text
440142 php tools/run-tests.php lanes/difftastic/tests lanes/esbuild/tests lanes/libsqlite/tests lanes/lightningcss/tests lanes/pandoc/tests lanes/quadrable/tests lanes/readability/tests
440152 php tools/run-tests.php
441852 php tools/run-tests.php lanes/markerpdf/tests lanes/rclone/tests
441858 php tools/run-tests.php lanes/syncthing/tests
```

The no-argument root PID `440152` exited before owner sampling. A
contemporaneous owner sample caught focused PHP harness PID `441858` as
`claude`:

```text
441858 441766 claude 00:41 R+ php tools/run-tests.php lanes/syncthing/tests
```

Later exact gate samples cleared, but I still did not start a root run because
active writer/status/test-control loops, moving `HEAD`, and a broad dirty
aggregate made the result non-comparable.

Current manifest/status sample versus the published dashboard:

| Lane | Current manifest mapped / denominator | Current lane PHP/status count | Published dashboard mapped / denominator |
| --- | ---: | ---: | ---: |
| difftastic | 346 / prose `687` artifacts | 346 | 160 / 417 |
| dolt | 610 / prose focused-runner status string | 332 PASS cases | 242 / 613 |
| esbuild | 287 / prose `2567` entry points | 287 | 164 / 2567 |
| gitoxide | 2684 / 2877 | 5306 assertions | 1432 / 2877 |
| libsqlite | 271 / 1589 | 270 | 149 / 1454 |
| LightningCSS | 1703 / 3532 | 2090 assertions | 773 / 3532 |
| markerPDF | 262 / 313 static behavior/reference units | 396 | 159 / 78 |
| pandoc | 905 / prose `2276` artifacts | 257 | 426 / 2028 |
| quadrable | 55 / prose `55` paths plus scenarios | 177 | 55 / 55 |
| rclone | 638 / 1601 | 638 | 291 / 327 |
| readability | 1984 / 1984 upstream Mocha tests | 186 native PHP tests | 1031 / 1984 |
| syncthing | 525 / 658 | 3799 assertions | 235 / 658 |

## Findings

1. **Critical - active writers, moving `HEAD`, and duplicate PHP harness waves
   still block a trustworthy aggregate baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-capacity-executor-queue.sh`, and sampled
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable tested commits, integration cleanup, and honest repo-wide
     verification.
   - Evidence: the required gate observed no-argument root PID `440152`
     running `php tools/run-tests.php`; `HEAD` later moved to `7b6005d22e22`;
     active process sampling still reported `21` worker/status/test-control
     matches and `95` tmux sessions; and the tree still had `248` tracked dirty
     rows. A root result from this state would not be an accepted baseline.

2. **Critical - the public coordination files no longer satisfy the
   current-status contract.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`, and
     `porting-summary.json:2` through `porting-summary.json:213`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current tracking and visible
     progress in the generated dashboard.
   - Evidence: `porting.html` and `porting-summary.json` still publish
     generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while sampled `HEAD` is `7b6005d22e22`. The Active Lanes
     table says every lane is stopped with `5%` to `66%` estimates, while
     lane-status files now claim `86%` to `99%`. Dashboard counts also disagree
     with current manifests, including rclone `291 / 327` versus `638 / 1601`,
     markerPDF `159 / 78` versus `262 / 313`, and syncthing `235 / 658` versus
     manifest `525 / 658` plus `3799` PHP assertions.

3. **High - near-complete lane percentages are not backed by accepted
   implementation commits.**
   - Paths: `lanes/difftastic/lane-status.json:4` and `:13`,
     `lanes/dolt/lane-status.json:4` and `:13`,
     `lanes/esbuild/lane-status.json:4` and `:13`,
     `lanes/gitoxide/lane-status.json:4` and `:13`,
     `lanes/libsqlite/lane-status.json:4` and `:13`,
     `lanes/lightningcss/lane-status.json:4` and `:13`,
     `lanes/markerpdf/lane-status.json:4` and `:13`,
     `lanes/pandoc/lane-status.json:4` and `:13`,
     `lanes/quadrable/lane-status.json:4` and `:13`,
     `lanes/rclone/lane-status.json:4` and `:13`,
     `lanes/readability/lane-status.json:4` and `:13`, and
     `lanes/syncthing/lane-status.json:4` and `:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`, and
     `goal.md:48` require small correct slices, passing tests, commits, and
     integration cleanup before assigning more work.
   - Evidence: lane statuses report `86%` to `99%`, but every sampled
     `latestCommit` is pending, uncommitted, or dirty-batch prose. Branch
     history after `b75226d1` contains only audit refresh and integration-hold
     commits.

4. **High - manifest denominator and PHP-count schemas remain non-normalized
   and sometimes internally inconsistent.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14` and `:2208`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14` and `:30`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14` through `:18`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through `:18`,
     `lanes/readability/lane-status.json:5` through `:6`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14` through `:15`, and
     `lanes/syncthing/lane-status.json:5` through `:6`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as source of truth, and comparable dashboard
     fields.
   - Evidence: `benchmarkDenominator.total` mixes integers, prose artifact
     inventories, and runner-status paragraphs. Dolt's `total` is a latest
     focused-runner status paragraph, not a stable upstream denominator.
     Readability claims `1984 / 1984` mapped upstream Mocha tests while native
     PHP has only `186` focused behavior tests; Syncthing manifest mapped
     count is `525` while lane `phpPass` is `3799` assertions.

5. **High - markerPDF and Readability still overstate native parity from
   static, supplied, copied, or oracle evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through `:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:357`,
     `lanes/markerpdf/lane-status.json:5` through `:13`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through `:18`,
     and `lanes/readability/lane-status.json:5` through `:13`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native standard-PHP ports,
     bridge/generated/shell-out non-progress handling, meaningful fixture
     parity, and explicit hard-feature gaps.
   - Evidence: markerPDF counts `262 / 313` static behavior/reference units
     while `runnerStatus` is `not-executed` and full upstream parity remains
     blocked by heavy Python/PDF/model/runtime dependencies. Readability
     records full upstream JavaScript Mocha coverage and copied all Mozilla
     fixtures, but native PHP coverage is still `186` focused behavior tests;
     that is useful evidence, not full native parity.

6. **Medium - blocker fields still mix slice-local green checks with full-port
   parity gaps.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers, parity beyond passing local
     tests, and explicit hard-feature gaps.
   - Evidence: blockers start with "No ... blocker" while the same fields
     disclose pending aggregate root verification, unexecuted full upstream
     runners, live provider/model requirements, excluded service coverage, or
     broad hydration needs. Slice-local status needs to be separated from
     full-port blockers.

## Test Gate

I did not run `php tools/run-tests.php`.

The first required duplicate-root gate observed active no-argument root PID
`440152`; later exact samples cleared, but the tree was still not stable enough
for a trustworthy root run because active writer/status/test-control loops,
moving `HEAD`, and broad dirty lane changes persisted.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
exact duplicate-root gate is empty across two polls.
