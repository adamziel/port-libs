# Independent Audit - 2026-05-23T18:16:02Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, sampled
`lanes/*/lane-status.json`, recent Git history, current worktree state, and
process state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

Sampled `HEAD`: `ae3a4ce4eb87` (`Refresh independent audit status`). The latest
30 sampled commits are audit-only refresh commits; the nearest recent
implementation commit remains `b75226d1` (`Port rclone OneDrive Object.Update
upload selection`).

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
porting-summary.json` passed at this sample.

## Current Snapshot

The tree is not quiescent. Samples moved during the audit: manifest mapped
counts changed while being read, including Dolt `599` to `600`, LightningCSS
`1494` to `1505`, and Readability `1971` to `1984`. The latest status sample
reported `2610` `git status --short --untracked-files=all` rows, `226` tracked
dirty rows, and `226 files changed, 105874 insertions(+), 9231 deletions(-)`.

I did not run `php tools/run-tests.php`. The required pre-root gate became
active after the first clear sample:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
3245877 php tools/run-tests.php
```

Owner evidence:

```text
3245877 claude 3245823 71 R php tools/run-tests.php
```

A later gate showed the root process had been replaced by active focused
Syncthing harnesses, so duplicate-root risk and writer churn were still
present:

```text
3255664 claude 3231174 5 Rs php tools/run-tests.php lanes/syncthing/tests/BepFrameStreamTest.php lanes/syncthing/tests/BepSessionTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php
3264353 claude 3231174 50 Rs php tools/run-tests.php lanes/syncthing/tests
```

Process sampling found `24` matching repo worker/status/test processes across
dashboard, watchdog, evaluator, capacity, lane-agent, integrator, auditor,
Dolt, SQLite, and PHP harness patterns while `progress.md` still reports every
lane as `stopped`.

| Lane | Current manifest mapped / denominator | Published dashboard mapped / denominator |
| --- | ---: | ---: |
| difftastic | 321 / 623 prose | 160 / 417 |
| dolt | 600 / 613 embedded in prose | 242 / 613 |
| esbuild | 271 / 2,567 | 164 / 2,567 |
| gitoxide | 2,179 / 2,877 | 1,432 / 2,877 |
| libsqlite | 259 / 1,589 | 149 / 1,454 |
| lightningcss | 1,505 / 3,532 | 773 / 3,532 |
| markerPDF | 250 / 302 | 159 / 78 |
| pandoc | 821 / 2,276 embedded in prose | 426 / 2,028 |
| quadrable | 55 / 55 | 55 / 55 |
| rclone | 575 / 1,601 | 291 / 327 |
| readability | 1,984 / 1,984, but only 175 native PHP behavior tests | 1,031 / 1,984 |
| syncthing | 471 / 658 | 235 / 658 |

## Findings

1. **Critical - active root/focused harnesses and live writers still block any
   trustworthy integration baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-php-dirty-root.sh`, and `lanes/*/lane-status.json:10`
     through `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:20` requires supervised parallelism
     capped to VM capacity; `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require committed, verified slices and honest repo-wide test recording.
   - Evidence: the required gate observed no-argument root PID `3245877` owned
     by `claude`, followed by focused Syncthing PIDs `3255664` and `3264353`
     owned by `claude`. The dirty tree had `226` tracked rows and over `105k` diff
     insertions. Manifest counts changed while being sampled.
   - Impact: a new root run would duplicate active harness work, and any root
     result from this moving tree would not be an accepted baseline.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and fail
   the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:65` and
     `porting-summary.json:2` through `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     per-lane dashboard data with upstream denominator, mapped tests, PHP
     pass/fail, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32` and `porting-summary.json:2` still publish
     `2026-05-23 04:57:16 UTC`; `porting.html:33` through `porting.html:36`
     and `porting-summary.json:3` through `porting-summary.json:5` still point
     at snapshot `bda83c6b93d4`, while sampled `HEAD` is `ae3a4ce4eb87`.
   - Evidence: current manifests disagree with the dashboard for every lane
     except Quadrable. Severe examples: markerPDF is now `250 / 302`, but the
     dashboard says `159 / 78`; rclone is `575 / 1601`, but the dashboard says
     `291 / 327`; LightningCSS is `1505 / 3532`, but the dashboard says `773 /
     3532`.
   - Evidence: `porting.html:41` through `porting.html:50` still lacks separate
     upstream-denominator and PHP pass/fail columns, and rows such as
     `porting.html:54` through `porting.html:65` mix PHP pass/fail and mapped
     coverage into one cell.

3. **High - `progress.md` contradicts the live supervision state and the active
   lane status files.**
   - Paths: `progress.md:14`, `progress.md:25`, and `progress.md:31` through
     `progress.md:42`.
   - Goal requirement at risk: `goal.md:20` and `goal.md:44` require accurate
     active lanes, owner/session state, blockers, next task, and percentage
     estimates.
   - Evidence: `progress.md:25` documents a launch target of two
     implementation lanes plus one auditor, but process sampling found `24`
     matching repo worker/status/test processes. The Active Lanes table reports
     every lane as `stopped` with estimates from `5%` to `66%`, while current
     lane status files show active or recently active work, pending root
     verification, and estimates as high as `98%` to `99%`.
   - Impact: the human coordination file is no longer reliable enough to drive
     capacity, acceptance, or next-work decisions.

4. **High - dirty lane batches are being counted in status before acceptance.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`, `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`, `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`, `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small,
     reviewable, tested, committed slices before integration.
   - Evidence: sampled `latestCommit` fields say `pending`, `uncommitted`, `not
     committed`, or dirty-worktree prose across the portfolio. The latest 30
     commits are audit-only refresh commits, and the nearest implementation
     commit is still `b75226d1`.
   - Impact: focused lane tests and upstream probes are useful evidence, but
     they cannot count as accepted implementation progress until isolated,
     verified from a stable tree, committed, and regenerated into the dashboard
     from the same snapshot.

5. **High - manifest denominator, mapped-count, and PHP-count schemas remain
   non-comparable across lanes.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:674` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:680`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:17`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:45`
     require real upstream denominators, mapped upstream tests, PHP pass/fail
     counts, and comparable dashboard fields.
   - Evidence: Dolt stores its denominator as a mutable prose log with `613`
     embedded in text and includes both green and red focused rerun evidence on
     the same line. Pandoc and Quadrable also use prose totals, while other
     lanes use numbers. Readability now reports `1984 / 1984` mapped at the top
     of the manifest, but its own warning says native PHP maps only `175` local
     behavior tests and `2434` local assertions against the Mocha denominator.
   - Impact: average progress and "near complete" claims are mixing upstream
     test entry points, files, fixtures, selected references, local assertions,
     copied fixtures, and prose runner logs.

6. **High - near-complete percentages understate full-parity blockers.**
   - Paths: `lanes/gitoxide/lane-status.json:4` through
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/readability/lane-status.json:4` through
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and `goal.md:40`
     require precise blockers and hard unported features to be recorded as
     blockers or future slices.
   - Evidence: Gitoxide claims `98%` while full cargo workspace runner parity
     and broad protocol/refspec/merge gaps remain. Readability claims `99%`
     while the native PHP behavior layer is still `175` focused tests against a
     `1984`-test upstream denominator and root aggregate verification is
     pending. Syncthing claims `98%` while the full upstream `go test ./...`
     remains unexecuted and broad checkout/module hydration is still a recorded
     gap.
   - Audit judgment: "no blocker" is only defensible for the current focused
     slice. The coordination data needs separate slice-local blockers and
     full-parity blockers before percentages can be trusted.

## Test Gate

I did not run `php tools/run-tests.php`.

The required gate before any possible root run is:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

This gate found active harnesses during the audit, including no-argument root
PID `3245877` owned by `claude` and later focused Syncthing PIDs `3255664` and
`3264353` owned by `claude`. A root run from this auditor would have duplicated
active test work and would not have produced an accepted baseline from the
moving tree.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops.
Then validate every manifest from the frozen tree, normalize manifest/status
denominator, mapped, PHP pass/fail, runner, blocker, and commit fields, accept
or reject dirty lane batches one lane at a time, regenerate `progress.md`,
`porting.html`, and `porting-summary.json` from the same accepted snapshot, and
only then capture one quiesced `php tools/run-tests.php` root run if the exact
duplicate-root gate remains empty.
