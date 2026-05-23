# Independent Audit - 2026-05-23T17:55:20Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
lane status files, recent Git history, current worktree state, and process
state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

Sampled `HEAD`: `d2e8cb40d450` (`Refresh independent audit status`). In the
latest 70 sampled commits, the nearest implementation commit is `b75226d1`
(`Port rclone OneDrive Object.Update upload selection`) after 52 newer
audit-only `Refresh independent audit status` commits.

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
porting-summary.json` passed at this sample.

## Current Snapshot

The tree is not quiescent. `git status --short --untracked-files=all | wc -l`
reported `2540` rows, tracked-only status reported `224` rows, and
`git diff --shortstat` reported `224 files changed, 103381 insertions(+), 8916
deletions(-)`.

The required duplicate-root gate returned no rows at the initial sampled
checks:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

A broader process sample briefly showed focused lane harness PID `2943221`
(`php tools/run-tests.php lanes/quadrable/tests`), but it exited before owner
sampling and was not a no-argument root run. A final exact handoff gate later
matched active focused lane harnesses `2948022`, `2948998`, and `2949044`.
Owner evidence:

```text
2948022 claude 2947798 00:31 R+ php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
2949044 claude 2949007 00:07 R  php tools/run-tests.php lanes/syncthing/tests/...
```

`2948998` exited before owner sampling. I still did not run
`php tools/run-tests.php` because the stability gate failed: process sampling
showed 20 active dashboard, watchdog, evaluator, capacity-controller,
integrator, auditor, Dolt-runner, and lane-agent loops while `progress.md`
still reports every lane as `stopped`.

| Lane | Current manifest mapped / denominator | Published dashboard mapped / denominator |
| --- | ---: | ---: |
| difftastic | 318 / 620 | 160 / 417 |
| dolt | 598 / 613 embedded in prose | 242 / 613 |
| esbuild | 268 / 2,567 | 164 / 2,567 |
| gitoxide | 2,179 / 2,877 | 1,432 / 2,877 |
| libsqlite | 257 / 1,589 | 149 / 1,454 |
| lightningcss | 1,458 / 3,532 | 773 / 3,532 |
| markerPDF | 246 / 298 | 159 / 78 |
| pandoc | 817 / 2,276 | 426 / 2,028 |
| quadrable | 55 / 55 | 55 / 55 |
| rclone | 568 / 1,601 | 291 / 327 |
| readability | 1,958 / 1,984 | 1,031 / 1,984 |
| syncthing | 468 / 658 | 235 / 658 |

## Findings

1. **Critical - there is still no stable integration baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`,
     and `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:20` requires capped supervised
     parallelism; `goal.md:29`, `goal.md:48`, `goal.md:49`, and `goal.md:52`
     require committed, verified, visible progress.
   - Evidence: the exact duplicate-root gate was clear, but active writer and
     status loops persisted and the dirty tree was `2540` status rows deep.
     Process sampling found 20 matching repo processes, including dashboard,
     team watchdog, evaluator, capacity controller, auditor, integrator,
     Dolt-runner, and many lane agents.
   - Impact: a fresh aggregate root test would race active writers and could
     not be accepted as the required baseline.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and do
   not satisfy the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:65`,
     `porting-summary.json:2` through `porting-summary.json:80`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     per-lane denominator, mapped tests, PHP pass/fail, WordPress scenarios,
     phase, audit, current work, blocker, and commit fields.
   - Evidence: `porting.html:32` still says generated
     `2026-05-23 04:57:16 UTC`; `porting.html:33` still publishes source
     snapshot `bda83c6b93d4`, while sampled `HEAD` is `d2e8cb40d450`.
   - Evidence: current manifests disagree with the dashboard for every mapped
     count except Quadrable. Severe examples: markerPDF is `246 / 298` current
     versus `159 / 78` published, rclone is `568 / 1601` versus `291 / 327`,
     and Readability is `1958 / 1984` versus `1031 / 1984`.
   - Evidence: `porting.html:41` through `porting.html:50` still lacks
     separate upstream-denominator and PHP pass/fail columns; rows such as
     `porting.html:54` through `porting.html:65` mix PHP pass/fail and mapped
     coverage in one cell.

3. **High - `progress.md` contradicts the live supervision state.**
   - Paths: `progress.md:14`, `progress.md:25`, and `progress.md:31` through
     `progress.md:42`.
   - Goal requirement at risk: `goal.md:20` and `goal.md:44` require accurate
     active lanes, owners/sessions, blockers, latest commit, next task, and
     percentage estimates.
   - Evidence: `progress.md:25` documents a launch target of two
     implementation lanes plus one auditor, while process sampling found the
     full status/control stack plus many lane agents active at once.
   - Evidence: the Active Lanes table still reports all sessions as `stopped`
     with stale estimates such as Gitoxide `66%`, LightningCSS `14%`,
     markerPDF `10%`, libsqlite `12%`, and Dolt `5%`, while current lane status
     files report 80-99% estimates and much later dirty-batch slices.
   - Evidence: `progress.md:14` leaves the independent auditor loop unchecked
     despite repeated audit-refresh commits and an active `port-auditor` loop.

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
   - Evidence: every sampled `latestCommit` field says `pending`,
     `uncommitted`, `not committed`, or equivalent dirty-worktree prose.
   - Impact: focused lane evidence may be useful, but it cannot count as
     accepted implementation progress until each batch is isolated, verified,
     committed, and reflected consistently in the dashboard/status files.

5. **High - manifest and status schemas remain internally inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:598`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:557`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:674`,
     and `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:45`
     require real upstream denominators, mapped upstream tests, PHP pass/fail
     counts, and comparable dashboard fields.
   - Evidence: Dolt's `benchmarkDenominator.total` is a long runner log with
     `613` embedded in prose, while other lanes use numeric totals.
   - Evidence: stale internal warnings disagree with current fields:
     difftastic reports `318 / 620` at the top but warns about `316 / 619`;
     markerPDF reports `246 / 298` but warns about `245 / 297`; Syncthing
     reports `468 / 658` but warns about `462`; Readability's manifest records
     `phpBehaviorTests: 172` while `lane-status.json` reports 174 focused PHP
     tests.

6. **High - near-complete percentages understate remaining full-parity gaps.**
   - Paths: `lanes/difftastic/lane-status.json:4` through
     `lanes/difftastic/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:4` through
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:4` through
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:4` through
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:4` through
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:4` through
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and `goal.md:40`
     require precise blockers and hard features to be recorded as blockers or
     future slices.
   - Evidence: multiple lanes now claim 96-99% progress while root integration
     remains pending, current work is uncommitted, and full upstream runners or
     full provider/model/integration suites remain explicitly unexecuted.
   - Audit judgment: "no blocker" is only valid for the focused slice. The
     coordination data needs separate slice-local blockers and full-parity
     blockers.

7. **Medium - copied fixtures, static inventories, and bounded oracle evidence
   are still overweighted as native progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:327`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:573`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, and `goal.md:37`
     say generated fixtures, bridge calls, shell-outs, static inventories, and
     shallow fixture parity are not native implementation progress by
     themselves.
   - Evidence: markerPDF runner status remains `not-executed`; Readability
     maps `1958` upstream units while its PHP behavior count is only 172-174;
     rclone and Syncthing rely on bounded/static runner evidence rather than
     full provider/protocol suite parity.

## Test Gate

I did not run `php tools/run-tests.php`.

Required gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

The exact gate returned no rows at the initial sampled checks, then matched
focused lane harness PIDs `2948022`, `2948998`, and `2949044` at final handoff.
Owner evidence confirmed `2948022` and `2949044` were owned by `claude`;
`2948998` exited before owner sampling. Root verification was withheld because
active writer/status processes and dirty lane batches persisted.

Validation run during this audit:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
```

## Next Intervention

Freeze active lane agents, dashboard/evaluator/capacity/watchdog/auditor loops,
and duplicate PHP harnesses. Validate manifests from the frozen tree, enforce
atomic writes for manifest/status/dashboard files, accept or reject dirty lane
batches one lane at a time, normalize denominator/mapped/PHP
pass-fail/runner/commit fields, regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from one accepted snapshot, then run
one quiesced root `php tools/run-tests.php` only after the exact duplicate-root
gate remains empty.
