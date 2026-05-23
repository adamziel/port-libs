# Independent Audit - 2026-05-23T17:36:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, recent
Git history, current worktree state, process state, lane statuses, and the
required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless they are explicitly temporary
oracle tooling.

Sampled `HEAD`: `f0ceb6cc0bcd` (`Refresh independent audit status`). The latest
48 commits before the nearest sampled implementation commit are audit-only
`Refresh independent audit status` commits; the nearest sampled implementation
commit is `b75226d1` (`Port rclone OneDrive Object.Update upload selection`).

## Current Snapshot

All lane manifests and `porting-summary.json` parse with `jq empty` at this
sample. The sampled worktree has `2418` total `git status --short` rows,
including `222` tracked dirty rows. `git diff --shortstat` reports `222 files
changed, 99893 insertions(+), 8825 deletions(-)`.

| Lane | Manifest denominator | Manifest mapped | Lane-status PHP pass/fail | Lane-status estimate | Lane-status commit |
| --- | ---: | ---: | ---: | ---: | --- |
| difftastic | 617 inspected artifacts | 312 | 312 / 0 | 96% | pending in shared dirty worktree |
| dolt | 613 executable test files, embedded in a narrative `total` field | 596 | 319 / 0 | 95% | not committed |
| esbuild | 2,567 upstream entry points | 266 | 266 / 0 | 80% | uncommitted lane batch |
| gitoxide | 2,877 upstream files | 2,176 | 4,152 / 0 | 98% | pending |
| libsqlite | 1,589 upstream units | 255 | 255 / 0 | 98% | uncommitted lane-scoped changes |
| lightningcss | 3,532 behavior checks | 1,444 | 1,637 / 0 | 89% | uncommitted shared worktree batch |
| markerPDF | 297 static/reference units | 245 | 378 / 0 | 96% | uncommitted lane batch |
| pandoc | 2,276 inspected artifacts | 805 | 241 / 0 | 98% | pending |
| quadrable | 55 tracked paths | 55 | 166 / 0 | 99% | pending lane batch |
| rclone | 1,601 Go test/benchmark/example units | 558 | 552 / 0 | 98% | pending lane-local changes |
| readability | 1,984 Mocha tests | 1,926 | 172 / 0 | 99% | uncommitted Salon slice |
| syncthing | 658 Go entry points | 459 | 3,250 / 0 | 98% | pending lane-local slice |

## Findings

1. **Critical - there is still no stable integration baseline, and the required duplicate-root gate is active.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`,
     and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:20` requires capped supervised lane
     work; `goal.md:29`, `goal.md:48`, and `goal.md:49` require small
     reviewable verified slices and honest repo-wide test evidence.
   - Evidence: the required exact gate matched an active no-argument root
     harness: `2857609 php tools/run-tests.php`, with owner evidence
     `2857609 claude 2855136 00:09 Rs php tools/run-tests.php`. I did not
     start a duplicate root run.
   - Evidence: process sampling also showed active dashboard, team-watchdog,
     evaluator, capacity-controller, auditor, integrator, Dolt-runner, and
     lane-agent watchdogs for most primary lanes while `progress.md:31` through
     `progress.md:42` still report every lane as `stopped`.
   - Evidence: the dirty-tree sample reported `2418` total status rows, `222`
     tracked dirty rows, and `222 files changed, 99893 insertions(+), 8825
     deletions(-)`.
   - Impact: a fresh aggregate `php tools/run-tests.php` result would race
     active writers and cannot be accepted as a repeatable baseline.

2. **Critical - `porting.html` and `porting-summary.json` are stale and still miss the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:65`, and
     `porting-summary.json:1` through `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52`
     require current progress tracking and a generated dashboard with separate
     per-lane upstream denominator, mapped tests, PHP pass/fail, phase, audit,
     blocker, and commit fields.
   - Evidence: `porting.html:32` says generated `2026-05-23 04:57:16 UTC`,
     and `porting.html:33` publishes snapshot `bda83c6b93d4`, while sampled
     `HEAD` is `f0ceb6cc0bcd`.
   - Evidence: dashboard rows disagree with current manifests/status files:
     Difftastic is published as `160 / 417` but current is `312 / 617`; Dolt is
     `242 / 613` but current mapped is `596`; esbuild is `164 / 2567` but
     current mapped is `266`; Gitoxide is `1432 / 2877` but current mapped is
     `2176`; libsqlite is `149 / 1454` but current is `255 / 1589`;
     LightningCSS is `773 / 3532` but current mapped is `1444`; markerPDF is
     `159 / 78` but current is `245 / 297`; Pandoc is `426 / 2028` but current
     is `805 / 2276`; rclone is `291 / 327` but current is `558 / 1601`;
     Readability is `1031 / 1984` but current mapped is `1926`; Syncthing is
     `235 / 658` but current mapped is `459`.
   - Evidence: `porting.html:41` through `porting.html:50` still lacks
     separate upstream-denominator and PHP pass/fail columns; rows at
     `porting.html:54` through `porting.html:65` mix PHP counts and mapped
     coverage in one cell.

3. **High - `progress.md` contradicts live supervision and lane status, so it is not a reliable operator surface.**
   - Paths: `progress.md:14`, `progress.md:25`, and `progress.md:31` through
     `progress.md:42`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to track
     active lanes, current owner/session, next task per lane, estimates,
     blockers, and latest commit.
   - Evidence: `progress.md:25` documents a two-implementation-lane plus one
     auditor launch target, while the process sample showed active lane-agent
     watchdogs for Gitoxide, markerPDF, Quadrable, rclone, Difftastic,
     LightningCSS, libsqlite, Pandoc, Dolt, Readability, Syncthing, esbuild,
     plus integrator/auditor/Dolt-runner loops and dashboard/evaluator loops.
   - Evidence: the Active Lanes table still reports stale estimates such as
     Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, Dolt `5%`, and
     esbuild `8%`, while current lane-status files report `98%`, `89%`,
     `96%`, `95%`, and `80%` respectively.
   - Evidence: `progress.md:14` still leaves the independent auditor loop
     unchecked even though audit-refresh commits and an active `port-auditor`
     watchdog are present.

4. **High - reported lane progress remains mostly unaccepted dirty-batch work, not reviewable integrated slices.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`, `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`, `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`, `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable commits, verification, progress updates, and
     honest failure recording.
   - Evidence: sampled `latestCommit` fields say pending, uncommitted, not
     committed, dirty worktree, or equivalent prose instead of reviewable
     accepted implementation commit IDs.
   - Evidence: the latest 48 commits before the nearest implementation commit
     are audit-only refreshes. The dirty work may contain valuable native PHP,
     but it is not integrated progress until accepted or rejected one lane at a
     time with coherent tests and a commit.

5. **High - manifest/status count schemas remain non-normalized and internally inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/*/lane-status.json:5` through `lanes/*/lane-status.json:7`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and
     `goal.md:45` require real denominators, mapped upstream tests, PHP
     pass/fail counts, and comparable dashboard fields.
   - Evidence: Dolt's denominator `total` is a long narrative field beginning
     with `Fresh runner-only 2026-05-23 17:27 UTC...`; the numeric denominator
     `613 upstream executable test files` is embedded later in prose, while
     `mapped` is `596` and lane-status PHP pass is `319`.
   - Evidence: Gitoxide reports `2176` mapped upstream units but `4152` PHP
     passes; markerPDF reports `245` mapped static/reference units but `378`
     PHP behavior tests; Readability reports `1926` mapped Mocha checks but
     `172` PHP tests; Syncthing reports `459` mapped checks but `3250` PHP
     assertions/tests depending on status wording.
   - Audit judgment: mapped upstream units, local PHP test cases, assertions,
     copied fixtures, runner evidence, and static inventory artifacts are all
     useful, but they are still being mixed as if they were one comparable
     progress count.

6. **High - near-complete estimates and blocker language still understate full upstream parity gaps.**
   - Paths: `lanes/difftastic/lane-status.json:4` and
     `lanes/difftastic/lane-status.json:12`,
     `lanes/dolt/lane-status.json:4` and `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:4` and `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:4` and `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:4` and `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:4` and `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/lane-status.json:4` and
     `lanes/readability/lane-status.json:12`, `lanes/rclone/lane-status.json:4`
     and `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:4` and `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and `goal.md:40`
     require precise blockers, real upstream parity, and hard features to be
     marked as blockers or future slices.
   - Evidence: most lanes report `estimatedProgress` in the 95-99 range while
     full upstream parity remains explicitly open: Difftastic full Cargo runner
     unavailable, Gitoxide full Cargo workspace runner unexecuted, Dolt full
     Go/BATS/server/provider suites unexecuted, Pandoc full Haskell runner
     unexecuted, markerPDF live Python/model workflows unexecuted, rclone live
     provider/mount parity open, and Syncthing full `go test ./...`
     unexecuted.
   - Audit judgment: "no blocker" is defensible only for the latest focused
     local slice. The coordination files need separate full-parity blockers and
     slice-local blockers so slice-green status is not read as lane parity.

7. **Medium - progress percentages still blend static inventories, copied fixtures, runner/oracle evidence, and native behavior.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:20`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:40`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:16`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:17`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, and
     `goal.md:39` say generated fixtures, bridge calls, shell-outs, static
     inventories, and shallow fixture parity do not count as native
     implementation progress by themselves.
   - Evidence: Difftastic is explicitly a cloned static inventory; markerPDF
     has no committed upstream Python tests and relies on static/reference
     units plus supplied-document excerpts; Readability maps 116 copied Mozilla
     fixtures and 1,715 fixture Mocha checks while focused PHP behavior tests
     are much smaller; rclone and Syncthing rely on bounded/static runner
     evidence rather than full provider/protocol suite parity.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

The gate matched an active no-argument root harness:

```text
2857609 php tools/run-tests.php
2857609 claude   2855136       00:09 Rs   php tools/run-tests.php
```

Validation run during this audit:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json
jq empty porting-summary.json
```

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops.
Then validate every manifest from the frozen tree, enforce atomic writes for
manifest/status/dashboard files, accept or reject dirty lane batches one lane at
a time, normalize denominator/mapped/PHP pass-fail/runner/commit fields,
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from that same accepted snapshot, and only then capture one quiesced
root `php tools/run-tests.php` run.
