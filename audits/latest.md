# Independent Audit - 2026-05-23T18:08:10Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, sampled
`lanes/*/lane-status.json`, recent Git history, current worktree state, and
process state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

Sampled `HEAD`: `585196ed2c2d` (`Refresh independent audit status`). The latest
20 sampled commits are audit-only refresh commits; the nearest recent
implementation commit remains `b75226d1` (`Port rclone OneDrive Object.Update
upload selection`).

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
porting-summary.json` passed at this sample.

## Current Snapshot

The tree is not quiescent. Samples moved during the audit; the latest status
sample reported `2603` `git status --short --untracked-files=all` rows, `228`
tracked dirty rows, and `228 files changed, 105636 insertions(+), 9373
deletions(-)`.

The required pre-root gate matched active PHP harnesses during the audit. I did
not run `php tools/run-tests.php`:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
3161556 php tools/run-tests.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php
3172845 php tools/run-tests.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/BepSessionTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/BepFrameStreamTest.php
3182840 php tools/run-tests.php lanes/syncthing/tests
```

Owner evidence from the latest active match:

```text
3182840 claude 2943732 00:12 Rs php tools/run-tests.php lanes/syncthing/tests
```

Process sampling ranged from `31` to `34` matching repo worker/status/test
processes across the dashboard, watchdog, evaluator, capacity, PHP harness,
lane-agent, Dolt, and SQLite patterns while `progress.md` still reports every
lane as `stopped`.

| Lane | Current manifest mapped / denominator | Published dashboard mapped / denominator |
| --- | ---: | ---: |
| difftastic | 318 / 620 | 160 / 417 |
| dolt | 599 / 613 embedded in prose | 242 / 613 |
| esbuild | 270 / 2,567 | 164 / 2,567 |
| gitoxide | 2,179 / 2,877 | 1,432 / 2,877 |
| libsqlite | 258 / 1,589 | 149 / 1,454 |
| lightningcss | 1,494 / 3,532 | 773 / 3,532 |
| markerPDF | 247 / 299 | 159 / 78 |
| pandoc | 821 / 2,276 embedded in prose | 426 / 2,028 |
| quadrable | 55 / 55 | 55 / 55 |
| rclone | 572 / 1,601 | 291 / 327 |
| readability | 1,971 / 1,984 | 1,031 / 1,984 |
| syncthing | 471 / 658 | 235 / 658 |

## Findings

1. **Critical - active writers and PHP harnesses still prevent a stable
   integration baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-php-dirty-root.sh`, and `lanes/*/lane-status.json:12`
     through `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:20` requires supervised parallelism
     capped to VM capacity; `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require committed, verified slices and honest repo-wide test recording.
   - Evidence: the required pre-root gate matched active Syncthing PHP harness
     PIDs `3161556`, `3172845`, and `3182840`, with owner evidence for
     `3182840` as `claude`. A later exact gate was clear, but the tree still
     had `228` tracked dirty rows and over `105k` diff insertions.
   - Impact: no root result captured from this moving tree can be treated as an
     accepted baseline, and starting another root harness would risk duplicating
     active test work.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and do
   not satisfy the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:65` and
     `porting-summary.json:2` through `porting-summary.json:120`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     per-lane dashboard data with benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, phase, audit, current work, blocker, and
     commit.
   - Evidence: `porting.html:32` and `porting-summary.json:2` still publish
     `2026-05-23 04:57:16 UTC`; `porting.html:33` and
     `porting-summary.json:3` through `porting-summary.json:5` still point at
     snapshot `bda83c6b93d4`, while sampled `HEAD` is `585196ed2c2d`.
   - Evidence: dashboard rows disagree with current manifests for every mapped
     count except Quadrable. Severe examples: markerPDF is currently `247 /
     299` but the dashboard says `159 / 78`; rclone is `572 / 1601` but the
     dashboard says `291 / 327`; LightningCSS is `1494 / 3532` but the
     dashboard says `773 / 3532`.
   - Evidence: `porting.html:41` through `porting.html:50` still lacks separate
     upstream-denominator and PHP pass/fail columns, and rows such as
     `porting.html:54` through `porting.html:65` mix PHP pass/fail and mapped
     coverage into one cell.

3. **High - `progress.md` contradicts the live supervision state.**
   - Paths: `progress.md:14`, `progress.md:25`, and `progress.md:31` through
     `progress.md:42`.
   - Goal requirement at risk: `goal.md:20` and `goal.md:44` require accurate
     active lanes, owner/session state, blockers, latest commit, next task, and
     percentage estimates.
   - Evidence: `progress.md:25` documents a launch target of two
     implementation lanes plus one auditor, but process sampling found `31` to
     `34` matching repo worker/status/test processes.
   - Evidence: the Active Lanes table reports all sessions as `stopped` with
     stale estimates from `5%` to `66%`; current lane status files report
     estimates from `80%` to `99%`, with many active or recently active lane
     harnesses.
   - Evidence: `progress.md:14` still leaves the independent auditor loop
     unchecked despite repeated audit-refresh commits and active audit/status
     processes.

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
     reviewable, verified, committed slices before progress is integrated.
   - Evidence: sampled `latestCommit` fields say `pending`, `uncommitted`, `not
     committed`, or dirty-worktree prose across the portfolio. Recent history
     is audit-only for the latest 20 commits, with the nearest implementation
     commit at `b75226d1`.
   - Impact: focused lane tests and upstream probes are useful evidence, but
     they cannot count as accepted implementation progress until each batch is
     isolated, verified from a stable tree, committed, and regenerated into the
     dashboard/status files from the same snapshot.

5. **High - manifest and status schemas remain non-comparable across lanes.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:2022`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:674` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:680`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1176`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:45`
     require real upstream denominators, mapped upstream tests, PHP pass/fail
     counts, and comparable dashboard fields.
   - Evidence: Dolt's `benchmarkDenominator.total` is a mutable prose log with
     `613` embedded in text while other lanes use numeric totals. Pandoc and
     Quadrable also encode denominator totals as long prose strings.
   - Evidence: Syncthing reports `471 / 658` at the top of its manifest while
     its warning still says native PHP maps `462` checks. Readability reports
     `1971 / 1984` mapped while its warning correctly admits only `175` native
     PHP behavior tests and `2434` assertions.
   - Impact: average progress and "near complete" claims are mixing upstream
     files, entry points, behavior checks, local assertions, PHP test cases,
     copied fixtures, and prose logs.

6. **High - near-complete lane percentages understate full-parity gaps.**
   - Paths: `lanes/difftastic/lane-status.json:4` through
     `lanes/difftastic/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:4` through
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/readability/lane-status.json:4` through
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and `goal.md:40`
     require precise blockers and hard unported features to be recorded as
     blockers or future slices.
   - Evidence: Difftastic claims `97%`, Gitoxide `98%`, Readability `99%`, and
     Syncthing `98%` while their own blocker text still records missing full
     upstream runner parity, pending aggregate root verification, broad
     dependency hydration, or unimplemented full upstream semantics.
   - Audit judgment: "no blocker" is only valid for a focused slice. The
     coordination data needs separate slice-local blockers and full-parity
     blockers before percentage estimates can be trusted.

7. **Medium - static inventories, bounded oracle evidence, and copied/generated
   fixture work are still overweighted as native progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:329`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:559`,
     `lanes/readability/lane-status.json:5`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:674`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:576`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1176`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, and `goal.md:37`
     say generated fixtures, bridge calls, shell-outs, static inventories, and
     shallow fixture parity are not native implementation progress by
     themselves.
   - Evidence: markerPDF runner status remains `not-executed`; Syncthing relies
     on bounded focused runners rather than full upstream package parity;
     Readability maps `119` copied Mozilla fixtures and `1971` upstream checks
     while native PHP behavior remains `175` focused local tests.

## Test Gate

I did not run `php tools/run-tests.php`.

Required gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

The gate matched active focused PHP harnesses during this audit, and the owner
sample showed PID `3182840` owned by `claude`. A later exact gate returned no
rows, but the worktree still failed the stability gate because active
worker/status processes and a large dirty aggregate remained present.

Validation run during this audit:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
```

## Next Intervention

Freeze active lane agents, dashboard/evaluator/capacity/watchdog/auditor loops,
duplicate root/focused PHP harnesses, Dolt BATS shards, and SQLite TCL runners.
Validate manifests from the frozen tree, enforce atomic writes for
manifest/status/dashboard files, accept or reject dirty lane batches one lane at
a time, normalize denominator/mapped/PHP pass-fail/runner/commit fields,
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from one accepted snapshot, then run exactly one quiesced root
`php tools/run-tests.php` only after the exact duplicate-root gate remains empty.
