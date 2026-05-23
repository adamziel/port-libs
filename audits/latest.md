# Independent Audit - 2026-05-23T17:52:45Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, recent Git
history, current worktree state, process state, and lane statuses.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

Sampled `HEAD`: `8af0907c77c1` (`Refresh independent audit status`). In the
latest 70 sampled commits, the nearest implementation commits were at positions
52 and 54: `b75226d1` (`Port rclone OneDrive Object.Update upload selection`)
and `90d1fa3b` (`Port rclone OneDrive multipart upload metadata`). The 51
newer commits are audit-only `Refresh independent audit status` commits.

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
porting-summary.json` passed at this sample.

## Current Snapshot

The sampled tree is still not quiescent. `git status --short
--untracked-files=all | wc -l` reported `2533` rows, tracked-only status
reported `224` rows, and `git diff --shortstat` reported `224 files changed,
102507 insertions(+), 9089 deletions(-)`.

The required duplicate-root gate returned no rows at the initial audit sample:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

The later pre-commit duplicate-root gate matched active focused Syncthing PHP
harness PID `2938085`; owner evidence was `2938085 claude 2927918 00:26 Rs php
tools/run-tests.php lanes/syncthing/tests`.

I still did not run `php tools/run-tests.php` because the tree was not stable
enough: process sampling showed at least 19 active dashboard, watchdog,
evaluator, capacity, integrator, auditor, and lane-agent processes, while
`progress.md` still reports every lane session as `stopped`.

| Lane | Current manifest mapped / denominator | Published dashboard mapped / denominator |
| --- | ---: | ---: |
| difftastic | 316 / 619 | 160 / 417 |
| dolt | 597 / 613 embedded in prose | 242 / 613 |
| esbuild | 268 / 2,567 | 164 / 2,567 |
| gitoxide | 2,179 / 2,877 | 1,432 / 2,877 |
| libsqlite | 257 / 1,589 | 149 / 1,454 |
| lightningcss | 1,458 / 3,532 | 773 / 3,532 |
| markerPDF | 246 / 298 | 159 / 78 |
| pandoc | 813 / 2,276 | 426 / 2,028 |
| quadrable | 55 / 55 | 55 / 55 |
| rclone | 563 / 1,601 | 291 / 327 |
| readability | 1,958 / 1,984 | 1,031 / 1,984 |
| syncthing | 462 / 658 | 235 / 658 |

## Findings

1. **Critical - there is still no stable integration baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `lanes/*/lane-status.json:13`, `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`, `scripts/run-evaluator-loop.sh`,
     and `scripts/run-capacity-controller-loop.sh`.
   - Goal requirement at risk: `goal.md:20` requires capped supervised
     parallelism; `goal.md:29`, `goal.md:48`, `goal.md:49`, and `goal.md:52`
     require verified, committed, visible progress.
   - Evidence: the exact duplicate-root gate was clear at the first sample,
     but a later pre-commit sample matched focused Syncthing PHP harness PID
     `2938085`, owned by `claude`. The broader stability gate also failed with
     at least 19 active repo worker/status processes and a dirty tree of
     `2533` status rows, `224` tracked dirty rows, and `102507` insertions in
     tracked diffs.
   - Evidence: the last 51 commits are audit-only refreshes, while every
     sampled lane status still records pending/uncommitted dirty-batch handoff
     text in `latestCommit`.
   - Impact: a fresh aggregate root test would race active writers and could
     not be accepted as the required baseline.

2. **Critical - `porting.html` and `porting-summary.json` remain stale and do
   not satisfy the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:65`,
     `porting-summary.json:2` through `porting-summary.json:18`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     per-lane tracking with separate upstream denominator, mapped tests, PHP
     pass/fail, WordPress scenarios, phase, audit, current work, blocker, and
     commit columns.
   - Evidence: `porting.html:32` still says generated
     `2026-05-23 04:57:16 UTC`, and `porting.html:33` still publishes snapshot
     `bda83c6b93d4`, while sampled `HEAD` is `8af0907c77c1`.
   - Evidence: published mapped counts disagree with every current manifest
     except Quadrable. The most severe examples are markerPDF `159 / 78`
     published versus `246 / 298` current, rclone `291 / 327` versus
     `563 / 1601`, and Readability `1031 / 1984` versus `1958 / 1984`.
   - Evidence: `porting.html:41` through `porting.html:50` still lacks
     separate upstream-denominator and PHP pass/fail columns; `porting.html:54`
     through `porting.html:65` mix PHP pass/fail and mapped coverage in one
     cell.

3. **High - `progress.md` contradicts the live supervision state.**
   - Paths: `progress.md:14`, `progress.md:25`, and `progress.md:31` through
     `progress.md:42`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to track
     active lanes, owners/sessions, blockers, latest commit, next task, and
     percentage estimates.
   - Evidence: `progress.md:25` still documents a launch target of two
     implementation lanes plus one auditor, but process sampling found active
     dashboard/team-watchdog/evaluator/capacity/integrator/auditor and many
     lane-agent loops.
   - Evidence: the Active Lanes table still reports all sessions as `stopped`
     and estimates such as Gitoxide `66%`, LightningCSS `14%`, markerPDF
     `10%`, libsqlite `12%`, and Dolt `5%`, while current status files record
     much later near-complete dirty-batch slices.
   - Evidence: `progress.md:14` still leaves the independent auditor loop
     unchecked despite repeated audit-refresh commits and an active
     `port-auditor` watchdog.

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
     reviewable slices with passing tests, progress updates, and integration.
   - Evidence: sampled `latestCommit` fields all say `pending`,
     `uncommitted`, dirty worktree, not committed, or equivalent prose.
   - Impact: focused lane evidence may be useful, but it cannot count as
     accepted implementation progress until each batch is isolated, verified,
     committed, and reflected consistently in the dashboard/status files.

5. **High - manifest denominator and status schemas remain non-normalized and
   non-comparable.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:564`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:674`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:45`
     require real upstream denominators, mapped upstream tests, PHP pass/fail
     counts, and comparable dashboard fields.
   - Evidence: Dolt's `benchmarkDenominator.total` is a long runner log that
     embeds the useful `613` denominator inside prose, while Quadrable's total
     is also a sentence and other lanes use numeric totals.
   - Evidence: PHP behavior counts and mapped upstream counts are mixed:
     markerPDF reports `mapped: 246` and `phpBehaviorTests: 378`; Readability
     reports `mapped: 1958` and `phpBehaviorTests: 172`.
   - Impact: static inventory artifacts, upstream runner cases, mapped
     behavior units, copied fixtures, PHP tests, assertions, and current slice
     evidence are still being blended into one progress number.

6. **High - near-complete lane status still understates full upstream parity
   gaps.**
   - Paths: `lanes/difftastic/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and `goal.md:40`
     require precise blockers and hard features to be recorded as blockers or
     future slices.
   - Evidence: status text repeatedly says no focused PHP blocker while full
     parity remains unexecuted: Difftastic full Cargo runner, Gitoxide full
     Cargo workspace runner, Pandoc full Haskell runner, markerPDF live
     Python/model workflows, rclone live provider/mount parity, Readability
     exact fixture parity, and Syncthing full `go test ./...`.
   - Audit judgment: "no blocker" is valid only for a focused slice. The
     coordination data needs separate slice-local blockers and full-parity
     blockers.

7. **Medium - copied fixtures, static inventories, and oracle evidence remain
   overweighted as native progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:327`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:40`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:623`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:570`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, and `goal.md:37`
     say generated fixtures, bridge calls, shell-outs, static inventories, and
     shallow fixture parity are not native implementation progress by
     themselves.
   - Evidence: markerPDF's runner status remains `not-executed`; Readability
     maps `1958` upstream units while its PHP behavior count is only `172`;
     rclone and Syncthing rely on bounded/static runner evidence rather than
     full provider/protocol suite parity.

## Test Gate

I did not run `php tools/run-tests.php`.

Required gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

The initial exact gate sample returned no rows. A later pre-commit sample
matched active focused Syncthing PHP harness PID `2938085`; owner evidence:

```text
2938085 claude 2927918 00:26 Rs php tools/run-tests.php lanes/syncthing/tests
```

A final exact gate sample returned no rows, but root verification was still
withheld because active writer/status processes and dirty lane batches persisted.

Validation run during this audit:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
```

## Next Intervention

Freeze active lane agents, dashboard/evaluator/capacity/watchdog/auditor loops,
and any duplicate PHP harnesses. Validate manifests from the frozen tree,
enforce atomic writes for manifest/status/dashboard files, accept or reject
dirty lane batches one lane at a time, normalize denominator/mapped/PHP
pass-fail/runner/commit fields, regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from one accepted snapshot, then run
one quiesced root `php tools/run-tests.php` only after the exact duplicate-root
gate remains empty.
