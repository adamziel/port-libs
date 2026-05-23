# Independent Audit - 2026-05-23T20:22:33Z

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

Current sampled `HEAD`: `b0c24174133d`.
The nearest sampled implementation commit is still `b75226d1`; `git rev-list
--count b75226d1..HEAD` reports `100` commits after it, consisting of `74`
`Refresh independent audit status` commits and `26` `Record integration hold
status` commits.

The worktree is not quiescent. Latest samples reported `3308`
`git status --short --untracked-files=all` rows, `247` tracked dirty rows, and
`247 files changed, 98482 insertions(+), 12709 deletions(-)`. Process sampling
reported `22` repo worker/status/test-control script matches and `94` tmux
sessions while `progress.md:25` documents a target of two implementation lanes
plus one auditor and `progress.md:31` through `progress.md:42` still mark every
lane as `stopped`.

The required exact root-harness gate:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

was initially clear, then matched active PHP harnesses during the audit,
including an active no-argument root harness:

```text
416117 php tools/run-tests.php lanes/difftastic/tests lanes/esbuild/tests lanes/libsqlite/tests lanes/lightningcss/tests lanes/pandoc/tests lanes/quadrable/tests lanes/readability/tests
416135 php tools/run-tests.php
416135 claude 415764 13 R+ php tools/run-tests.php
416170 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
```

The final exact gate sample was clear, but I did not start a root run because
active writers/status publishers and the dirty aggregate still made a root
result non-comparable.

Current manifest/status sample versus the published dashboard:

| Lane | Current manifest mapped / denominator | Current lane PHP/status count | Published dashboard mapped / denominator |
| --- | ---: | ---: | ---: |
| difftastic | 346 / prose `687` artifacts | 346 | 160 / 417 |
| dolt | 610 / prose focused-runner status string | 331 PASS cases | 242 / 613 |
| esbuild | 287 / prose `2567` entry points | 287 | 164 / 2567 |
| gitoxide | 2683 / 2877 | 5295 assertions/checks | 1432 / 2877 |
| libsqlite | 270 / 1589 | 270 | 149 / 1454 |
| LightningCSS | 1667 / 3532 | 2055 assertions | 773 / 3532 |
| markerPDF | 262 / 313 static behavior/reference units | 395 | 159 / 78 |
| pandoc | 905 / prose `2276` artifacts | 257 | 426 / 2028 |
| quadrable | 55 / prose `55` paths plus scenarios | 176 | 55 / 55 |
| rclone | 631 / 1601 | 638 | 291 / 327 |
| readability | 1984 / 1984 upstream Mocha tests | 186 native PHP tests | 1031 / 1984 |
| syncthing | 519 / 658 | 3742 assertions | 235 / 658 |

Manifest/status values changed during audit sampling, including Difftastic,
esbuild, and rclone counts. That reinforces the non-quiescent-tree finding.

## Findings

1. **Critical - active writers and duplicate test/control loops still block a
   trustworthy aggregate baseline.**
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
   - Evidence: the required gate observed root PID `416135` owned by `claude`
     running `php tools/run-tests.php`. Even when the final exact gate cleared,
     worker/status/test-control loops and 94 tmux sessions persisted, with
     247 tracked dirty files. Starting another root run would create
     non-comparable evidence.

2. **Critical - `progress.md`, `porting.html`, and `porting-summary.json` do
   not satisfy the current-status contract.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`, and
     `porting-summary.json:2` through `porting-summary.json:119`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current tracking and visible
     progress in the generated dashboard.
   - Evidence: the dashboard still publishes generated time `2026-05-23
     04:57:16 UTC` and source commit `bda83c6b93d4`, while sampled `HEAD` is
     `b0c24174133d`. The Active Lanes table says every lane is stopped with
     stale `5%` to `66%` estimates, while current lane-status files report
     `85%` to `99%` and live agents are running. Dashboard rows materially
     disagree with current manifests, including rclone `291 / 327` versus
     `631 / 1601`, markerPDF `159 / 78` versus `262 / 313`, and Difftastic
     `160 / 417` versus `346 / 687`.

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
   - Evidence: current lane statuses report `85%` to `99%` progress, but every
     sampled `latestCommit` is `pending`, `uncommitted`, or dirty-batch prose.
     Current branch history after `b75226d1` contains only audit refresh and
     integration-hold commits.

4. **High - manifest denominator and PHP-count schemas remain non-normalized
   and internally inconsistent.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14` and `:2205`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through `:15`,
     `lanes/readability/lane-status.json:5` through `:6`, and
     `lanes/syncthing/lane-status.json:5` through `:6`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as source of truth, and comparable dashboard
     fields.
   - Evidence: `benchmarkDenominator.total` mixes integers, prose artifact
     inventories, and runner-status paragraphs. Dolt's `total` is a focused
     PHP/root-runner status paragraph, not a stable upstream denominator.
     Readability claims `1984 / 1984` mapped upstream Mocha tests while native
     PHP has only `186` focused behavior tests; Syncthing manifest mapped
     count is `519` while lane `phpPass` is `3742` assertions.

5. **High - markerPDF and Readability still overstate native parity from
   static, supplied, or copied evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through `:16`,
     `lanes/markerpdf/lane-status.json:5` and `:12`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through `:15`, and
     `lanes/readability/lane-status.json:5` and `:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native standard-PHP ports,
     bridge/generated/shell-out non-progress handling, meaningful fixture
     parity, and explicit hard-feature gaps.
   - Evidence: markerPDF counts `262 / 313` static behavior/reference units
     while its lane status still says full upstream benchmarks, live Streamlit,
     FastAPI/Uvicorn, Python multiprocessing, OCR, and model-worker paths are
     not executed. Readability records full upstream JavaScript Mocha coverage
     and copied all Mozilla fixtures, but native PHP coverage is still `186`
     focused behavior tests and cannot be reported as full native parity.

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
   - Evidence: blockers begin with "No ... blocker" while the same fields
     disclose pending aggregate root verification, unexecuted full upstream
     runners, live provider/model requirements, excluded service coverage, or
     broad hydration needs. Slice-local blocker status needs a separate field
     from full-port parity blockers.

## Test Gate

I did not run `php tools/run-tests.php`.

The required duplicate-root gate observed active no-argument root PID `416135`
owned by `claude` during this audit. A final exact gate later cleared, but the
tree was still not stable enough for a trustworthy root run because active
writer/status/test-control loops and broad dirty lane changes persisted.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
exact duplicate-root gate is empty across two polls.
