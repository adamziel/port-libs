# Independent Audit - 2026-05-23T20:46:52Z

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

Sampled `HEAD`: `70b8118b687c`.

Latest sampled worktree state:

```text
260 files changed, 100647 insertions(+), 11598 deletions(-)
260 tracked dirty rows
3601 total git status rows
```

Recent Git history remains status/audit dominated:

```text
70b8118b Record integration hold status
ccb77fee Refresh independent audit status
146828c6 Record integration hold status
d0406456 Record integration hold status
59eccc10 Refresh independent audit status
cda27274 Record integration hold status
5be7f583 Refresh independent audit status
7b6005d2 Record integration hold status
```

The required exact root-harness gate was checked before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
699197 php tools/run-tests.php
707318 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterTest.php lanes/syncthing/tests/ProtocolValidationTest.php lanes/syncthing/tests/PullDbUpdaterTest.php lanes/syncthing/tests/PullFinisherTest.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php lanes/syncthing/tests/SentDownloadStateTest.php
710016 php tools/run-tests.php lanes/syncthing/tests
```

Owner evidence:

```text
PID      USER    PPID    ELAPSED  STAT  COMMAND
699197   claude  699065  01:46    R+    php tools/run-tests.php
707318   claude  707145  00:45    R+    php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterTest.php lanes/syncthing/tests/ProtocolValidationTest.php lanes/syncthing/tests/PullDbUpdaterTest.php lanes/syncthing/tests/PullFinisherTest.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php lanes/syncthing/tests/SentDownloadStateTest.php
710016   claude  642864  00:24    Rs    php tools/run-tests.php lanes/syncthing/tests
```

I did not start `php tools/run-tests.php` because a no-argument root harness was
already active. A later exact gate showed the no-argument root had exited but a
focused Syncthing shard was still active:

```text
717706 claude 717559 00:42 R+ php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php lanes/syncthing/tests/ProgressEmitterTest.php lanes/syncthing/tests/ProtocolValidationTest.php lanes/syncthing/tests/PullDbUpdaterTest.php lanes/syncthing/tests/PullFinisherTest.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php
```

The tree remained unstable, so I still did not start a root run.

Current manifest/status sample versus the published dashboard:

| Lane | Current manifest mapped / denominator | Current lane status | Published dashboard mapped / denominator |
| --- | ---: | ---: | ---: |
| difftastic | 350 / prose 705 artifacts | 350 pass, 99% | 160 / 417 |
| dolt | 611 / prose latest-runner total | 334 pass cases, 95% | 242 / 613 |
| esbuild | 289 / 2567 | 289 pass, 86% | 164 / 2567 |
| gitoxide | 2690 / 2877 | 5344 PHP assertions, 98% | 1432 / 2877 |
| libsqlite | 272 / 1589 | 272 pass, 98% | 149 / 1454 |
| LightningCSS | 1711 / 3532 | 2094 PHP assertions, 93% | 773 / 3532 |
| markerPDF | 264 / 315 | 398 pass, 98% | 159 / 78 |
| pandoc | 920 / prose 2276 artifacts | 259 pass, 99% | 426 / 2028 |
| quadrable | 55 / prose 55 paths plus scenarios | 178 pass, 99% | 55 / 55 |
| rclone | 642 / 1601 | 642 pass, 99% | 291 / 327 |
| readability | 1984 / 1984 upstream Mocha tests | 188 pass, 99% | 1031 / 1984 |
| syncthing | 531 / 658 | 3843 PHP assertions, 99% | 235 / 658 |

## Findings

1. **Critical - a root harness is already active, and the tree is still too
   unstable for a trustworthy aggregate checkpoint.**
   - Paths: `tools/run-tests.php`, `progress.md:25`,
     `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-capacity-executor-queue.sh`, `.tmux-team/tmp/port-*.md`,
     and `.tmux-team/logs/*`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable commits, integration cleanup, and honest repo-wide
     verification.
   - Evidence: the exact gate matched active no-argument root PID `699197`
     owned by `claude`. Process sampling also showed active lane agents plus
     dashboard/evaluator/watchdog/capacity/integrator/auditor loops while
     `progress.md` still claims all lanes are stopped. The dirty tree is a
     large moving aggregate, not an accepted test target.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict current manifests/status files.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`,
     `porting-summary.json`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json` files.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current progress tracking and a
     visible dashboard with denominator, mapped, PHP pass/fail, phase, audit,
     blocker, and commit fields.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 04:57:16 UTC` and snapshot `bda83c6b93d4`, while sampled
     `HEAD` is `70b8118b687c`. Current rclone is `642 / 1601` mapped while
     the dashboard shows `291 / 327`; markerPDF is `264 / 315` while the
     dashboard shows `159 / 78`; Syncthing is `531 / 658` while the dashboard
     shows `235 / 658`.

3. **High - near-complete lane percentages are tied to unaccepted dirty
   batches, not committed integration points.**
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
   - Evidence: every sampled lane status reports 86-99% progress, but
     `latestCommit` fields are pending, uncommitted, or dirty-batch prose. The
     latest sampled commits are audit/status/integration-hold updates rather
     than accepted implementation commits.

4. **High - manifest denominator and PHP-count schemas remain non-normalized,
   so the percentages are not comparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
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
   - Evidence: `benchmarkDenominator.total` mixes integers, prose artifact
     summaries, path inventories, and latest-runner paragraphs. PHP counts mix
     behavior tests, PASS cases, and assertions: Gitoxide reports `5322`
     assertions, Syncthing reports `3843` assertions, Dolt reports `334` PASS
     cases, and Readability maps `1984 / 1984` upstream Mocha tests while only
     `188` focused native PHP tests are recorded.

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
   - Evidence: markerPDF reports 98% and 398 PHP passes, but its evidence is
     still dominated by static inventory, supplied-document slices, reference
     prep/planner boundaries, and model/runtime-adjacent behavior rather than
     an executed upstream PDF/model benchmark. Readability maps all `1984`
     upstream Mocha checks and copied all Mozilla fixtures, but native parity
     is represented by `188` focused PHP tests; upstream JavaScript oracle
     success is useful evidence, not full native PHP parity.

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
   - Evidence: many blocker fields start with "No ... blocker" while the same
     text discloses pending root verification, unexecuted full upstream
     runners, excluded live-provider/server/model paths, or broad parity gaps.
     Slice-local green status needs a separate field from full-port blockers.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact gate matched active no-argument root PID `699197`, owned by
`claude`. A later exact gate showed only a focused Syncthing shard, but the tree
remained unstable with active writers/status loops, so launching a root harness
would still reduce confidence in aggregate evidence.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP harnesses.
Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
