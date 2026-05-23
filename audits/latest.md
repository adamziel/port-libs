# Independent Audit - 2026-05-23T20:06:54Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
sampled `lanes/*/lane-status.json`, recent Git history, worktree state, and
process state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, copied oracle
fixtures, and shell-outs are treated as non-progress unless explicitly
temporary oracle tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

`HEAD` was `b6ab788944e9` (`Record integration hold status`) at the latest
sample. Recent history remains coordination-only on the current branch: the
nearest sampled implementation commit is still `b75226d1` (`Port rclone
OneDrive Object.Update upload selection`), and `git rev-list --count
b75226d1..HEAD` reports `94` commits: `72` titled `Refresh independent audit
status` and `22` titled `Record integration hold status`.

The worktree is not quiescent. Latest samples reported `3133`
`git status --short --untracked-files=all` rows, `240` tracked dirty rows, and
`239 files changed, 95833 insertions(+), 12577 deletions(-)`. Process sampling
reported `38` repo worker/status/test-control matches and `95` tmux sessions
while `progress.md:25` documents a target of two implementation lanes plus one
auditor and `progress.md:31` through `progress.md:42` still mark every lane as
`stopped`.

The required exact root-harness gate:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

matched active no-argument root harnesses during the final audit/commit
samples:

```text
294435 php tools/run-tests.php
294435 claude 294205 13 R+ php tools/run-tests.php
330409 php tools/run-tests.php
330409 claude 330254 19 R+ php tools/run-tests.php
```

I did not start a duplicate root run. The broader process sample also showed
active primary lane agents, dashboard/evaluator/watchdog/capacity/auditor and
integrator loops, plus a broad Dolt BATS shard.

Current manifest/status sample versus the published dashboard:

| Lane | Current manifest mapped / denominator | Current lane PHP/status count | Published dashboard mapped / denominator |
| --- | ---: | ---: | ---: |
| difftastic | 342 / prose `669` artifacts | 342 | 160 / 417 |
| dolt | 609 / prose root/focused-runner status string | 331 PASS cases | 242 / 613 |
| esbuild | 284 / prose `2,567` entry points | 284 | 164 / 2,567 |
| gitoxide | 2670 / 2877 | 5258 assertions/checks | 1432 / 2877 |
| libsqlite | 269 / 1589 | 269 | 149 / 1454 |
| LightningCSS | 1645 / 3532 | 2026 | 773 / 3532 |
| markerPDF | 260 / 311 static behavior/reference units | 394 | 159 / 78 |
| pandoc | 891 / prose `2276` artifacts | 255 | 426 / 2028 |
| quadrable | 55 / prose `55` paths plus scenarios | 175 | 55 / 55 |
| rclone | 626 / 1601 | 628 | 291 / 327 |
| readability | 1984 / 1984 upstream Mocha tests | 185 native PHP tests | 1031 / 1984 |
| syncthing | 514 / 658 | 3683 assertions | 235 / 658 |

## Findings

1. **Critical - an active root harness and active writer/test loops block a
   trustworthy aggregate baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`,
     and sampled `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable tested commits, integration cleanup, and honest repo-wide
     verification.
   - Evidence: the exact no-argument root gate matched PID `294435` and then
     PID `330409`, both owned by `claude`, so a duplicate root run was
     prohibited. Even apart from that, active lane agents, status publishers,
     capacity jobs, evaluator/auditor and integrator loops, broad Dolt BATS
     activity, `95` tmux sessions, and `240` tracked dirty rows make any
     aggregate root result non-comparable.

2. **Critical - `progress.md`, `porting.html`, and `porting-summary.json` fail
   the current-status contract.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`, and
     `porting-summary.json:2` through `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current tracking and visible
     progress in the generated dashboard.
   - Evidence: the dashboard still publishes generated time `2026-05-23
     04:57:16 UTC` and source commit `bda83c6b93d4`, while current sampled
     `HEAD` is `b6ab788944e9`. The Active Lanes table says every lane is
     stopped with stale estimates as low as `5%` to `66%`, while current
     lane-status files report `84%` to `99%` and live agents are running.
     Dashboard rows materially disagree with manifests, including rclone
     `291 / 327` versus current `626 / 1601`, markerPDF `159 / 78` versus
     current `260 / 311`, and Difftastic `160 / 417` versus current
     `342 / 669`.

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
   - Evidence: sampled lane statuses now report `84%` to `99%` progress, but
     every sampled `latestCommit` is `pending`, `uncommitted`, or dirty-batch
     prose. Current branch history after `b75226d1` contains only audit refresh
     and integration-hold commits, while the worktree still contains broad
     uncommitted lane implementation changes.

4. **High - manifest denominator and PHP-count schemas remain
   non-normalized and internally inconsistent.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2207`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14` through `:15`,
     `lanes/rclone/lane-status.json:5` through `:6`,
     and `lanes/syncthing/lane-status.json:5` through `:6`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as source of truth, and comparable dashboard
     fields.
   - Evidence: `benchmarkDenominator.total` mixes numbers, prose inventories,
     and process-status paragraphs. Dolt's `total` is a focused PHP/root-runner
     status paragraph, not an upstream denominator. Rclone has manifest mapped
     `626` but lane status says native PHP maps `628`; Syncthing manifest maps
     `514` while lane status `phpPass` is `3683` assertions. Readability marks
     `1984 / 1984` upstream Mocha tests mapped while native PHP has only `185`
     behavior tests.

5. **High - markerPDF and Readability still overstate native parity from
   static, supplied, or copied evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through `:19`,
     `lanes/markerpdf/lane-status.json:5` and `:12`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through `:16`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:720` through `:726`, and
     `lanes/readability/lane-status.json:5` and `:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native standard-PHP ports,
     bridge/generated/shell-out non-progress handling, meaningful fixture
     parity, and explicit hard-feature gaps.
   - Evidence: markerPDF counts `260 / 311` static behavior/reference units
     with supplied-document and plan-only boundaries while Python/Streamlit,
     FastAPI, OCR/model, multiprocessing, and benchmark workflows remain
     unexecuted. Readability records the upstream JavaScript Mocha runner as
     `1984 / 1984` and copied all Mozilla fixtures, but native PHP coverage is
     still `185` focused behavior tests and should not be reported as full
     native parity.

6. **Medium - blocker fields still blur slice-local green checks with full-port
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
     `goal.md:40` require precise blockers, parity beyond passing local tests,
     and explicit hard-feature gaps.
   - Evidence: blockers begin with "No ... blocker" while the same fields
     disclose pending aggregate root verification, unexecuted full upstream
     runners, live provider/model requirements, excluded service coverage, or
     broad hydration needs. Slice-local blocker status needs a separate field
     from full-port parity blockers.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact duplicate-root gate matched active no-argument root PIDs
`294435` and later `330409`, both owned by `claude`, so starting another root
harness was prohibited. The tree also failed the stability gate because active
writer/status/test loops and broad dirty lane changes persisted.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP harnesses.
Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
exact duplicate-root gate is empty across two polls.
