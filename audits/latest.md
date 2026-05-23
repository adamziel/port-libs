# Independent Audit - 2026-05-23T20:50:22Z

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

Sampled `HEAD`: `222bbf58bdfd`.

Latest sampled worktree state:

```text
258 files changed, 100929 insertions(+), 11447 deletions(-)
3718 total git status rows
103 tmux sessions
```

Recent Git history remains status/audit dominated:

```text
222bbf58 Record integration hold status
01bb8a7f Refresh independent audit status
70b8118b Record integration hold status
ccb77fee Refresh independent audit status
146828c6 Record integration hold status
d0406456 Record integration hold status
59eccc10 Refresh independent audit status
cda27274 Record integration hold status
```

The nearest sampled implementation commit is still `b75226d1`, now 111 commits
behind `HEAD`.

The required exact root-harness gate was checked before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
729379 php tools/run-tests.php
738558 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php lanes/syncthing/tests/ProgressEmitterTest.php lanes/syncthing/tests/ProtocolValidationTest.php lanes/syncthing/tests/PullDbUpdaterTest.php lanes/syncthing/tests/PullFinisherTest.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php
738745 php tools/run-tests.php
738760 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
```

Owner evidence captured immediately after the gate:

```text
729379 claude 729108 01:21 R+ php tools/run-tests.php
738558 claude 738345 00:16 R+ php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php lanes/syncthing/tests/ProgressEmitterTest.php lanes/syncthing/tests/ProtocolValidationTest.php lanes/syncthing/tests/PullDbUpdaterTest.php lanes/syncthing/tests/PullFinisherTest.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php
738745 claude 738404 00:15 R+ php tools/run-tests.php
```

PID `738760` exited before owner sampling. I did not start
`php tools/run-tests.php` because at least two no-argument root harnesses were
already active.

Current lane status sample versus the published dashboard:

| Lane | Current status mapped / denominator | Current PHP count | Current estimate | Dashboard mapped / denominator |
| --- | ---: | ---: | ---: | ---: |
| difftastic | 350 / prose 705 artifacts | 350 pass | 99% | 160 / 417 |
| dolt | 611 / prose latest-runner total | 334 pass cases | 95% | 242 / 613 |
| esbuild | 290 / prose 2,567 entry points | 290 pass | 86% | 164 / 2,567 |
| gitoxide | 2696 / 2877 | 5344 assertions | 98% | 1432 / 2877 |
| libsqlite | 273 / 1589 | 272 pass | 98% | 149 / 1454 |
| LightningCSS | 1711 / 3532 | 2101 assertions | 94% | 773 / 3532 |
| markerPDF | 264 / 315 | 398 pass | 98% | 159 / 78 |
| pandoc | 926 / prose 2276 artifacts | 260 pass | 99% | 426 / 2028 |
| quadrable | 55 / prose 55 paths plus scenarios | 178 pass | 99% | 55 / 55 |
| rclone | 642 / 1601 | 642 pass | 99% | 291 / 327 |
| readability | 1984 / 1984 upstream Mocha tests | 189 pass | 99% | 1031 / 1984 |
| syncthing | 536 / 658 | 3896 assertions | 99% | 235 / 658 |

`porting-summary.json` also remains stale: generated
`2026-05-23 04:57:16 UTC`, source commit `bda83c6b93d4`, dashboard commit
`8ba77df82902`, average progress `68.8`.

## Findings

1. **Critical - duplicate no-argument root harnesses are active while the tree
   is still a moving, dirty aggregate.**
   - Paths: `tools/run-tests.php`, `progress.md:25`,
     `progress.md:31` through `progress.md:42`, `.tmux-team/logs/*`,
     `.tmux-team/tmp/port-*.md`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`, and
     `scripts/run-capacity-executor-queue.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable commits, integration cleanup, and honest repo-wide
     verification.
   - Evidence: the required exact gate matched two active no-argument root
     harnesses, PIDs `729379` and `738745`, both owned by `claude`.
     `progress.md` still says the launch target is two implementation lanes
     plus one auditor and lists all lanes as `stopped`, while the process gate,
     103 tmux sessions, and 3718 status rows show the repo is not quiesced.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   materially contradict the current manifests and lane statuses.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`,
     `porting-summary.json`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json` files.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current progress tracking and a
     visible dashboard with denominator, mapped, PHP pass/fail, phase, audit,
     blocker, and commit fields.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while
     sampled `HEAD` is `222bbf58bdfd`. Current rclone status is
     `642 / 1601` mapped, but the dashboard shows `291 / 327`; current
     markerPDF is `264 / 315`, but the dashboard shows `159 / 78`; current
     Syncthing is `536 / 658`, but the dashboard shows `235 / 658`.

3. **High - near-complete lane percentages are tied to pending dirty batches,
   not accepted integration commits.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`, and
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`,
     `goal.md:36`, and `goal.md:48` require small correct slices, passing
     tests, and verified committed handoffs before assigning the next work.
   - Evidence: every sampled lane reports 86-99% progress, but all current
     `latestCommit` fields are `pending`, `uncommitted`, `not committed`, or
     dirty-batch prose. The last sampled non-audit/status implementation commit
     remains `b75226d1`, 111 commits behind `HEAD`.

4. **High - manifest denominator and PHP-count schemas remain non-normalized,
   making cross-lane percentages incomparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as source of truth, and comparable dashboard
     fields.
   - Evidence: `benchmarkDenominator.total` is numeric for some lanes
     (`gitoxide`, `libsqlite`, `lightningcss`, `markerPDF`, `rclone`,
     `readability`, `syncthing`) but prose strings for others
     (`difftastic`, `dolt`, `esbuild`, `pandoc`, `quadrable`). PHP count fields
     mix behavior tests, BATS PASS cases, and assertions: Gitoxide reports
     `5344` assertions, Syncthing reports `3896` assertions, Dolt reports
     `334` PASS cases, and Readability maps `1984 / 1984` upstream Mocha tests
     while only `189` focused native PHP tests are recorded.

5. **High - markerPDF and Readability still overstate native parity from
   static, supplied, copied, or upstream-oracle evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/readability/lane-status.json`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native standard-PHP ports,
     generated/bridge evidence to not count as implementation progress,
     meaningful fixture parity, and explicit hard-feature gaps.
   - Evidence: markerPDF reports 98% and 398 PHP passes, but the manifest
     still says the full upstream runner was not executed and the evidence is
     dominated by static inventory, supplied-document slices, model/runtime
     plans, and no live Python/Poetry/Torch/Surya/pypdfium/PIL/OCR execution.
     Readability maps all `1984 / 1984` upstream Mocha tests after copied
     fixture and JS oracle work, but native PHP parity is represented by only
     `189` focused PHP tests; upstream JavaScript oracle success is useful
     reference evidence, not full native PHP parity.

6. **Medium - blocker fields still mix slice-local green checks with full-port
   blockers.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`,
     and `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers, parity beyond local passing tests,
     and explicit hard-feature gaps.
   - Evidence: many blocker fields begin with "No ... blocker" while the same
     text discloses pending root verification, unexecuted full upstream
     runners, excluded live-provider/server/model paths, or broad parity gaps.
     Slice-local green status needs a separate field from full-port blockers.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact gate matched active no-argument root PIDs `729379` and
`738745`, both owned by `claude`, so starting another aggregate harness would
violate the duplicate-run constraint and reduce confidence in the evidence.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP harnesses.
Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
