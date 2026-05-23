# Independent Audit - 2026-05-23T17:45:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, recent Git
history, current worktree state, process state, and lane statuses.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

Sampled `HEAD`: `779905763871` (`Refresh independent audit status`). In the
latest 55 sampled commits, the nearest implementation commits were at positions
51 and 53: `b75226d1` (`Port rclone OneDrive Object.Update upload selection`)
and `90d1fa3b` (`Port rclone OneDrive multipart upload metadata`). The 50
newer commits were audit-only `Refresh independent audit status` commits.

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
porting-summary.json` passed at this sample.

## Current Snapshot

The sampled worktree is not quiescent. `git status --short
--untracked-files=all | wc -l` reported `2511` rows, tracked-only status
reported `222` rows, and `git diff --shortstat` reported `222 files changed,
101965 insertions(+), 9109 deletions(-)`. Several manifest/status values moved
during the audit; for example `lanes/rclone/lane-status.json:13` changed from a
OneDrive API item slice to an OpenOption slice while the audit was running.

| Lane | Manifest denominator | Manifest mapped | Status estimate | Status commit |
| --- | ---: | ---: | ---: | --- |
| difftastic | 618 inspected artifacts | 314 | 96% | pending in shared dirty worktree |
| dolt | prose field containing 613 executable test files | 597 | 95% | not committed |
| esbuild | 2,567 entry points | 267 | 80% | uncommitted lane batch |
| gitoxide | 2,877 upstream files | 2,176 | 98% | pending |
| libsqlite | 1,589 upstream units | 257 | 98% | uncommitted lane-scoped changes |
| lightningcss | 3,532 behavior checks | 1,450 | 89% | uncommitted shared batch |
| markerPDF | 297 static/reference units | 245 | 96% | uncommitted lane batch |
| pandoc | 2,276 inspected artifacts | 813 | 98% | pending |
| quadrable | 55 tracked paths | 55 | 99% | pending lane batch |
| rclone | 1,601 Go test/benchmark/example units | 563 | 98% | pending lane-local changes |
| readability | 1,984 Mocha tests | 1,943 | 99% | uncommitted fixture slice |
| syncthing | 658 Go entry points | 462 | 98% | pending lane-local slice |

## Findings

1. **Critical - there is still no stable integration baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `lanes/*/lane-status.json:13`, and active repo loop scripts including
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and
     `scripts/run-capacity-controller-loop.sh`.
   - Goal requirement at risk: `goal.md:20` requires capped supervised
     parallelism; `goal.md:29`, `goal.md:48`, and `goal.md:49` require small
     reviewable commits, verification, integration, and honest repo-wide test
     evidence.
   - Evidence: the required duplicate-root gate matched an active PHP harness:
     `2928523 php tools/run-tests.php lanes/syncthing/tests/...`, with owner
     evidence `2928523 claude 2928485 00:12 R php tools/run-tests.php
     lanes/syncthing/tests/...`. I did not start a root run.
   - Evidence: process sampling also found `21` matching repo
     watchdog/dashboard/evaluator/capacity/tmux-agent/PHP-test processes, while
     `progress.md:31` through `progress.md:42` still reports every lane as
     `stopped`.
   - Evidence: the dirty tree has `2511` total status rows, `222` tracked dirty
     rows, and `222 files changed, 101965 insertions(+), 9109 deletions(-)`.
   - Impact: any fresh aggregate `php tools/run-tests.php` result would race
     active writers and cannot be accepted as the required stable baseline.

2. **Critical - `porting.html` and `porting-summary.json` are stale and still
   miss the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:65`, and
     `porting-summary.json:2` through `porting-summary.json:18`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     per-lane tracking with separate upstream denominator, mapped tests, PHP
     pass/fail, WordPress scenarios, phase, audit, current work, blocker, and
     commit columns.
   - Evidence: `porting.html:32` says generated `2026-05-23 04:57:16 UTC`,
     and `porting.html:33` publishes snapshot `bda83c6b93d4`, while sampled
     `HEAD` is `779905763871`.
   - Evidence: published mapped counts disagree with current manifests:
     Difftastic `160 / 417` versus current `314 / 618`; Dolt `242 / 613`
     versus `597 / 613`; esbuild `164 / 2567` versus `267 / 2567`; Gitoxide
     `1432 / 2877` versus `2176 / 2877`; libsqlite `149 / 1454` versus
     `257 / 1589`; LightningCSS `773 / 3532` versus `1450 / 3532`;
     markerPDF `159 / 78` versus `245 / 297`; Pandoc `426 / 2028` versus
     `813 / 2276`; rclone `291 / 327` versus `563 / 1601`; Readability
     `1031 / 1984` versus `1943 / 1984`; Syncthing `235 / 658` versus
     `462 / 658`.
   - Evidence: `porting.html:41` through `porting.html:50` lacks separate
     upstream-denominator and PHP pass/fail columns; `porting.html:54` through
     `porting.html:65` mix PHP pass/fail and mapped coverage in one cell.

3. **High - `progress.md` contradicts live supervision and current lane state.**
   - Paths: `progress.md:14`, `progress.md:25`, and `progress.md:31` through
     `progress.md:42`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to track
     active lanes, owners/sessions, blockers, latest commit, next task, and
     percentage estimates.
   - Evidence: `progress.md:25` still documents a launch target of two
     implementation lanes plus one auditor, but process sampling found
     dashboard/team-watchdog/evaluator/capacity/auditor/integrator loops and
     active lane agents across the portfolio.
   - Evidence: the Active Lanes table still reports old estimates such as
     Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, libsqlite `12%`,
     and Dolt `5%`, while current lane status files report `98%`, `89%`,
     `96%`, `98%`, and `95%`.
   - Evidence: `progress.md:14` leaves the independent auditor loop unchecked
     even though audit-refresh commits and an active `port-auditor` watchdog
     are present.

4. **High - most lane progress is dirty-batch work, not accepted goal progress.**
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
   - Evidence: all sampled lane `latestCommit` fields say `pending`,
     `uncommitted`, `not committed`, dirty worktree, or equivalent prose.
   - Evidence: the recent Git history is dominated by audit-only commits; the
     nearest sampled implementation commit is 50 commits behind `HEAD`.
   - Impact: the dirty work may be valuable, but it cannot count as accepted
     implementation progress until each lane batch is verified, committed, and
     reflected consistently in the dashboard/status files.

5. **High - manifest denominator/status schemas are still non-normalized and
   non-comparable.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`, and
     `lanes/*/lane-status.json:4` through `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and
     `goal.md:45` require real upstream denominators, mapped upstream tests,
     PHP pass/fail counts, and comparable dashboard fields.
   - Evidence: no manifest exposes top-level `mappedUpstreamTests`,
     `phpTests`, or top-level `runnerStatus`; all counts live under narrative
     `benchmarkDenominator` fields.
   - Evidence: Dolt's `benchmarkDenominator.total` at
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14` is a long run log that embeds
     the denominator inside prose, while rclone and markerPDF use numeric
     totals at `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14` and
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`.
   - Impact: static inventory artifacts, runner cases, mapped upstream units,
     PHP test cases, assertions, copied fixtures, and current slice evidence are
     useful, but they are still being mixed into one progress number.

6. **High - near-complete estimates still understate full upstream parity gaps.**
   - Paths: `lanes/difftastic/lane-status.json:4` and
     `lanes/difftastic/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:4` and `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:4` and `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:4` and `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:4` and `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:4` and `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:4` and
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:4` and `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers and hard features to be recorded as
     blockers or future slices.
   - Evidence: many lanes report `96%` to `99%` while full parity is explicitly
     not achieved: Difftastic full Cargo runner unavailable, Gitoxide full
     Cargo workspace runner unexecuted, Pandoc full Haskell runner unexecuted,
     markerPDF live Python/model workflows unexecuted, rclone live
     provider/mount parity open, Readability largely fixture-copy mapped, and
     Syncthing full `go test ./...` unexecuted.
   - Audit judgment: "no blocker" is valid only for the latest focused slice.
     The coordination data needs separate slice-local blockers and full-parity
     blockers.

7. **Medium - copied fixtures, static inventories, and oracle evidence remain
   overweighted as progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:16` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:25`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:40`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:16` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:18`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:17`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, and
     `goal.md:37` say generated fixtures, bridge calls, shell-outs, static
     inventories, and shallow fixture parity are not native implementation
     progress by themselves.
   - Evidence: markerPDF has `0` committed Python test files and relies on
     static/reference units plus supplied-document excerpts; Readability maps
     `117` copied Mozilla fixtures and `1943` mapped checks while local PHP
     behavior coverage is much smaller; rclone and Syncthing rely on bounded or
     static runner evidence rather than full provider/protocol suite parity.

## Test Gate

I did not run `php tools/run-tests.php`.

Required gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

The latest gate sample matched an active focused Syncthing PHP harness:

```text
2928523 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
2928523 claude 2928485 00:12 R php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
```

Even before that match, the tree was not stable enough for an accepted root run
because active writer/status processes and dirty lane batches persisted.

Validation run during this audit:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
```

## Next Intervention

Freeze active lane agents, status publishers, dashboard/evaluator/capacity
loops, and duplicate PHP harnesses. Validate every manifest from the frozen
tree, enforce atomic writes for manifest/status/dashboard files, accept or
reject dirty lane batches one lane at a time, normalize denominator/mapped/PHP
pass-fail/runner/commit fields, regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from one accepted snapshot, then run
one quiesced root `php tools/run-tests.php` only after the exact gate remains
empty.
