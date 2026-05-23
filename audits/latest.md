# Independent Audit - 2026-05-23T17:19:02Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, recent Git history, current worktree
state, process state, lane statuses, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

Sampled `HEAD`: `43412227e046` (`Refresh independent audit status`). The
latest 45 commits before the nearest sampled implementation commit are
audit-only `Refresh independent audit status` commits. The nearest recent
implementation commit sampled was `b75226d1` (`Port rclone OneDrive Object.Update
upload selection`).

## Current Snapshot

All lane manifests parse with `jq empty` at this sample. All sampled
`lanes/*/lane-status.json` files also parse.

| Lane | Current manifest denominator | Manifest mapped | Lane-status PHP pass/fail | Lane-status commit |
| --- | ---: | ---: | ---: | --- |
| difftastic | 615 inspected artifacts | 310 | 310 / 0 | pending in shared dirty worktree |
| dolt | prose field embedding 613 executable test files | 595 | 318 / 0 | not committed |
| esbuild | 2,567 upstream entry points | 265 | 265 / 0 | uncommitted lane batch |
| gitoxide | 2,877 upstream files | 2,171 | 4,131 / 0 | pending |
| libsqlite | 1,589 upstream units | 254 | 254 / 0 | uncommitted lane-scoped changes |
| lightningcss | 3,532 behavior checks | 1,440 | 1,632 / 0 | uncommitted shared worktree batch |
| markerPDF | 295 static/reference units | 243 | 373 / 0 | uncommitted lane batch |
| pandoc | 2,276 inspected artifacts | 799 | 239 / 0 | pending |
| quadrable | 55 tracked paths | 55 | 164 / 0 | pending lane batch |
| rclone | 1,601 Go test/benchmark/example units | 547 | 547 / 0 | pending lane-local changes |
| readability | 1,984 Mocha tests | 1,913 | 171 / 0 | uncommitted GMW slice |
| syncthing | 658 Go entry points | 454 | 3,183 / 0 | pending lane-local slice |

## Findings

1. **Critical - the root-test gate is actively blocked, and the tree is not stable enough for an aggregate baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`,
     and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:20` requires capped supervised lane
     work; `goal.md:29`, `goal.md:48`, and `goal.md:49` require small
     reviewable verified slices and honest repo-wide test evidence.
   - Evidence: the required duplicate-root gate returned active root PID
     `2776284 php tools/run-tests.php`; owner evidence was
     `2776284 claude 1 27 R php tools/run-tests.php`. A focused Syncthing PHP
     run was also briefly visible in the gate output before owner sampling.
   - Evidence: the worktree sample reported `2428` total status rows, `221`
     tracked dirty rows, and `221 files changed, 97728 insertions(+), 8740
     deletions(-)`.
   - Evidence: active dashboard, team-watchdog, evaluator, capacity-controller,
     integrator, auditor, Dolt runner, and all primary lane-agent processes were
     sampled while `progress.md:31` through `progress.md:42` still reports every
     lane session as `stopped`.
   - Impact: starting another root harness would violate the duplicate-root
     gate, and any aggregate result from this moving tree would not be an
     accepted integration checkpoint.

2. **Critical - `porting.html` and `porting-summary.json` are stale and still do not satisfy the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:65`, and `porting-summary.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52`
     require current progress tracking and a generated dashboard with per-lane
     upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker,
     and commit fields.
   - Evidence: `porting.html:32` says generated `2026-05-23 04:57:16 UTC`,
     and `porting.html:33` through `porting.html:36` publish snapshot
     `bda83c6b93d4`, while sampled `HEAD` is `43412227e046`.
   - Evidence: current manifests disagree with the published rows: Difftastic is
     published as `160 / 417` but current is `310 / 615`; Dolt is `242 / 613`
     but current mapped is `595`; esbuild is `164 / 2567` but current mapped is
     `265`; Gitoxide is `1432 / 2877` but current mapped is `2171`;
     libsqlite is `149 / 1454` but current is `254 / 1589`; LightningCSS is
     `773 / 3532` but current mapped is `1440`; markerPDF is `159 / 78` but
     current is `243 / 295`; pandoc is `426 / 2028` but current is `799 / 2276`;
     rclone is `291 / 327` but current is `547 / 1601`; Readability is
     `1031 / 1984` but current mapped is `1913`; Syncthing is `235 / 658` but
     current mapped is `454`.
   - Evidence: `porting.html:41` through `porting.html:50` still lacks separate
     upstream-denominator and PHP pass/fail columns; rows at `porting.html:54`
     through `porting.html:65` mix PHP counts and mapped coverage in one cell.

3. **High - `progress.md` contradicts the live supervision state.**
   - Paths: `progress.md:14`, `progress.md:25`, `progress.md:31` through
     `progress.md:42`, and `progress.md:367` through `progress.md:369`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to track
     active lanes, current owner/session, next task per lane, estimates,
     blockers, and latest commit.
   - Evidence: `progress.md:25` documents a target of two implementation lanes
     plus one auditor, but process sampling found the active watchdog/status
     loops plus lane agents for Difftastic, Dolt, esbuild, Gitoxide, libsqlite,
     LightningCSS, markerPDF, pandoc, Quadrable, rclone, Readability, and
     Syncthing.
   - Evidence: `progress.md:14` still leaves the independent auditor loop
     unchecked even though audit refresh commits and an active `port-auditor`
     watchdog are present.
   - Impact: the human-readable coordinator is no longer a reliable operator
     surface for owner/session state or capacity decisions.

4. **High - most lane progress is still unaccepted dirty-batch work, not small committed slices.**
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
   - Evidence: every sampled lane-status `latestCommit` field says pending,
     uncommitted, not committed, or current dirty work rather than a reviewable
     accepted implementation commit.
   - Evidence: the latest 45 commits before the nearest sampled implementation
     commit are audit-only refreshes touching `audits/latest.md` and
     `progress.md`.
   - Impact: the lane status files can describe useful local work, but the
     project cannot count it as integrated native-port progress until each
     dirty batch is accepted or rejected with coherent tests and a commit.

5. **High - manifest/status count schemas remain non-normalized and internally inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2129`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:654`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1160`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:45`, and
     `goal.md:52` require real denominators, mapped upstream tests, PHP
     pass/fail counts, and comparable dashboard fields.
   - Evidence: Dolt's denominator `total` is a long narrative field at
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`, while the denominator needed
     for comparison is embedded in prose as `613 upstream executable test
     files`; the same manifest's warning at line 2129 still says native PHP
     maps `317` behavior tests even though the header maps `595` and
     lane-status reports `318` PHP passes.
   - Evidence: LightningCSS reports `1440` mapped at line 15 and `1632` PHP
     passes in lane status, while the manifest warning prose still describes
     `1,438` focused checks.
   - Evidence: Syncthing reports `454` mapped at line 15 and `454` in
     `currentSlice`, while the manifest warning still says `453` focused lane
     checks at line 1160.
   - Evidence: Readability maps `1913` upstream checks at line 15, but its own
     warning at line 654 says native PHP has `171` local behavior tests and
     `2361` assertions. That may be valuable coverage, but it is not the same
     unit as mapped upstream denominator coverage.

6. **High - blocker language still understates full upstream parity gaps.**
   - Paths: `lanes/difftastic/lane-status.json:12`,
     `lanes/dolt/lane-status.json:12`, `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`, `lanes/libsqlite/lane-status.json:12`,
     `lanes/lightningcss/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`, `lanes/pandoc/lane-status.json:12`,
     `lanes/quadrable/lane-status.json:12`, `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and hard features to be marked as blockers or future slices.
   - Evidence: blockers commonly begin with "No current ... blocker" or "none
     for this slice" while also listing broad unexecuted work: full Cargo
     workspace parity, full Dolt runner parity, esbuild `make test-all`, full
     SQLite all/release permutations, live markerPDF Python/model workflows,
     full Pandoc Haskell runner, full rclone provider/mount parity, and full
     Syncthing `go test ./...`.
   - Audit judgment: "no blocker" is defensible only for the focused local
     slice. As written, these fields are too easy for the dashboard to present
     as lane parity.

7. **Medium - current progress percentages blend static inventories, copied fixtures, oracles, and native PHP behavior.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:654`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:934`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1160`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, and `goal.md:39`
     say generated fixtures, bridge calls, shell-outs, and shallow fixture
     parity do not count as native implementation progress by themselves.
   - Evidence: Difftastic, markerPDF, Pandoc, rclone, and Syncthing are still
     explicitly static or bounded-focused inventories rather than full upstream
     runner parity; Readability maps a large number of copied upstream fixtures
     while native PHP behavior tests remain much smaller than the upstream Mocha
     denominator.
   - Impact: the percentages may be useful as local lane momentum, but they
     should not be used as native parity percentages until the dashboard
     separates inventory coverage, oracle coverage, native behavior tests,
     assertions, and full upstream runner evidence.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Sample result:

```text
2776284 php tools/run-tests.php
2776826 php tools/run-tests.php lanes/syncthing/tests/BepConnectionLifecycleTest.php lanes/syncthing/tests/DeviceConnectionStateTest.php lanes/syncthing/tests/BepWireTest.php lanes/syncthing/tests/BepFrameStreamTest.php lanes/syncthing/tests/BepSessionTest.php
```

Owner evidence:

```text
2776284 claude 1 27 R php tools/run-tests.php
```

The focused Syncthing process exited before owner sampling. No duplicate root
harness was started.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops.
Then validate every manifest from the frozen tree, enforce atomic writes for
manifest/status/dashboard files, accept or reject dirty lane batches one lane at
a time, normalize denominator/mapped/PHP pass-fail/runner/commit fields,
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from that same accepted snapshot, and only then capture one quiesced
root `php tools/run-tests.php` run.
