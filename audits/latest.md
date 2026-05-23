# Independent Audit - 2026-05-23T17:24:11Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, recent
Git history, current worktree state, process state, lane statuses, and the
required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless they are explicitly temporary
oracle tooling.

Sampled `HEAD`: `89a0037f8aad` (`Refresh independent audit status`). The latest
46 commits before the nearest sampled implementation commit are audit-only
`Refresh independent audit status` commits; the nearest sampled implementation
commit is `b75226d1` (`Port rclone OneDrive Object.Update upload selection`).

## Current Snapshot

All lane manifests and sampled `lanes/*/lane-status.json` files parse with
`jq empty` at this sample.

| Lane | Current manifest denominator | Manifest mapped | Lane-status PHP pass/fail | Lane-status commit |
| --- | ---: | ---: | ---: | --- |
| difftastic | 615 inspected artifacts | 310 | 310 / 0 | pending in shared dirty worktree |
| dolt | prose field embedding 613 executable test files | 595 | 318 / 0 | not committed |
| esbuild | 2,567 upstream entry points | 265 | 265 / 0 | uncommitted lane batch |
| gitoxide | 2,877 upstream files | 2,171 | 4,131 / 0 | pending |
| libsqlite | 1,589 upstream units | 254 | 254 / 0 | uncommitted lane-scoped changes |
| lightningcss | 3,532 behavior checks | 1,440 | 1,632 / 0 | uncommitted shared worktree batch |
| markerPDF | 296 static/reference units | 244 | 374 / 0 | uncommitted lane batch |
| pandoc | 2,276 inspected artifacts | 803 | 240 / 0 | pending |
| quadrable | 55 tracked paths | 55 | 165 / 0 | pending lane batch |
| rclone | 1,601 Go test/benchmark/example units | 547 | 547 / 0 | pending lane-local changes |
| readability | 1,984 Mocha tests | 1,913 | 171 / 0 | uncommitted GMW slice |
| syncthing | 658 Go entry points | 456 | 3,204 / 0 | pending lane-local slice |

## Findings

1. **Critical - there is still no stable integration baseline, so a root run would not be trustworthy.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`,
     and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:20` requires capped supervised lane
     work; `goal.md:29`, `goal.md:48`, and `goal.md:49` require small
     reviewable verified slices and honest repo-wide test evidence.
   - Evidence: the exact duplicate-root gate returned no rows in the audit
     samples, but the tree failed the stability gate. Process sampling showed
     active dashboard (`2222131`), team-watchdog (`2347911`), evaluator
     (`2424048`), capacity-controller (`2452997`), auditor (`2785885`), a
     Dolt runner (`2780969`), and primary lane agents for Quadrable,
     Difftastic, Dolt, LightningCSS, libsqlite, rclone, Readability, esbuild,
     Gitoxide, markerPDF, Pandoc, and Syncthing.
   - Evidence: the worktree sample reported `2383` total status rows, `221`
     tracked dirty rows, and `221 files changed, 98578 insertions(+), 8774
     deletions(-)`. A later tracked-only sample still reported `221` dirty
     files and `221 files changed, 98578 insertions(+), 8774 deletions(-)`.
   - Impact: starting `php tools/run-tests.php` would create a non-repeatable
     aggregate result from a moving dirty tree. I did not start a root harness.

2. **Critical - `porting.html` and `porting-summary.json` are stale and still miss the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:65`, and
     `porting-summary.json:2` through `porting-summary.json:4`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52`
     require current progress tracking and a generated dashboard with per-lane
     upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker,
     and commit fields.
   - Evidence: `porting.html:32` says generated `2026-05-23 04:57:16 UTC`,
     and `porting.html:33` through `porting.html:36` publish snapshot
     `bda83c6b93d4`, while sampled `HEAD` is `89a0037f8aad`.
   - Evidence: dashboard rows disagree with current manifests/status files:
     Difftastic is published as `160 / 417` but current is `310 / 615`;
     Dolt is `242 / 613` but current mapped is `595`; esbuild is `164 / 2567`
     but current mapped is `265`; Gitoxide is `1432 / 2877` but current mapped
     is `2171`; libsqlite is `149 / 1454` but current is `254 / 1589`;
     LightningCSS is `773 / 3532` but current mapped is `1440`; markerPDF is
     `159 / 78` but current is `244 / 296`; Pandoc is `426 / 2028` but current
     is `803 / 2276`; rclone is `291 / 327` but current is `547 / 1601`;
     Readability is `1031 / 1984` but current mapped is `1913`; Syncthing is
     `235 / 658` but current mapped is `456`.
   - Evidence: `porting.html:41` through `porting.html:50` still lacks
     separate upstream-denominator and PHP pass/fail columns; rows at
     `porting.html:54` through `porting.html:65` mix PHP counts and mapped
     coverage in one cell.

3. **High - `progress.md` contradicts the live supervision state and is no longer a reliable operator surface.**
   - Paths: `progress.md:14`, `progress.md:25`, and `progress.md:31` through
     `progress.md:42`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to track
     active lanes, current owner/session, next task per lane, estimates,
     blockers, and latest commit.
   - Evidence: `progress.md:25` documents a target of two implementation lanes
     plus one auditor, but process sampling found dashboard/status loops plus
     active agents for all twelve primary lanes and additional capacity/auditor
     work. `progress.md:31` through `progress.md:42` still reports every lane
     session as `stopped`.
   - Evidence: `progress.md:14` still leaves the independent auditor loop
     unchecked even though audit-refresh commits and an active `port-auditor`
     watchdog are present.

4. **High - most reported lane progress is still unaccepted dirty-batch work, not reviewable integrated slices.**
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
     uncommitted, not committed, or dirty-batch prose rather than a reviewable
     accepted implementation commit.
   - Evidence: the latest 46 sampled commits before the nearest implementation
     commit are audit-only refreshes touching `audits/latest.md` and
     `progress.md`. The status files may describe useful local work, but the
     project cannot count that work as integrated native-port progress until
     each batch is accepted or rejected with coherent tests and a commit.

5. **High - manifest/status count schemas remain non-normalized and internally inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:36` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:38`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:17`, and
     `lanes/*/lane-status.json:5` through `lanes/*/lane-status.json:7`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:45`, and
     `goal.md:52` require real denominators, mapped upstream tests, PHP
     pass/fail counts, and comparable dashboard fields.
   - Evidence: Dolt's denominator `total` is a long narrative field at
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`; the numeric comparison unit
     is embedded in prose as `613 upstream executable test files`, while
     `mapped` is `595` and lane-status PHP pass is `318`.
   - Evidence: LightningCSS reports `1440` mapped in the manifest and `1632`
     PHP passes in lane status; markerPDF reports `244` mapped and `374` PHP
     passes; Pandoc reports `803` mapped and `240` PHP passes; Syncthing
     reports `456` mapped, `3204` PHP assertions/passes in lane status, and
     warning prose that still says `453` focused lane checks.
   - Audit judgment: mapped upstream units, local PHP test cases, assertions,
     copied fixtures, runner evidence, and static inventory artifacts are all
     useful, but they are being mixed as if they were one comparable count.

6. **High - blocker language and near-complete estimates still understate upstream parity gaps.**
   - Paths: `lanes/difftastic/lane-status.json:4` and
     `lanes/difftastic/lane-status.json:12`,
     `lanes/dolt/lane-status.json:4` and `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:4` and `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:4` and `lanes/gitoxide/lane-status.json:12`,
     `lanes/readability/lane-status.json:4` and
     `lanes/readability/lane-status.json:12`,
     `lanes/rclone/lane-status.json:4` and `lanes/rclone/lane-status.json:12`,
     and `lanes/syncthing/lane-status.json:4` and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and hard features to be marked as blockers or future slices.
   - Evidence: several lanes report `estimatedProgress` in the 95-99 range
     while also saying full upstream runners remain unexecuted or broad parity
     remains open: Difftastic full Cargo runner unavailable, Gitoxide full
     Cargo workspace runner unexecuted, esbuild `make test-all` separate,
     rclone live provider/mount parity open, Readability has 171 local behavior
     tests against 1,984 upstream Mocha checks, and Syncthing full
     `go test ./...` unexecuted.
   - Audit judgment: "no blocker" is defensible only for the latest focused
     local slice. The dashboard needs a separate full-parity blocker/future-work
     field so slice-green status is not read as lane parity.

7. **Medium - current progress percentages still blend static inventories, copied fixtures, and oracle evidence with native behavior.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:36` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:40`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:17`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, and `goal.md:39`
     say generated fixtures, bridge calls, shell-outs, static inventories, and
     shallow fixture parity do not count as native implementation progress by
     themselves.
   - Evidence: Difftastic is explicitly a cloned static inventory; markerPDF
     has no upstream Python test files and relies on static behavior/reference
     units plus external/surrogate/supplied benchmark pairs; Readability maps
     115 copied Mozilla fixture pages and 1,702 mapped fixture Mocha checks
     while native local behavior tests are much smaller; rclone and Syncthing
     rely on bounded/static runner evidence rather than full provider/protocol
     suite parity.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Main and follow-up exact-gate samples returned no rows. No duplicate root
harness was active at the sampled instants, but the tree was not stable enough
for a trustworthy aggregate root run because active writers/status publishers
and a broad dirty worktree persisted.

Validation run during this audit:

```text
for f in lanes/*/UPSTREAM_TEST_MANIFEST.json; do jq empty "$f" || exit 1; done
manifests-ok

for f in lanes/*/lane-status.json; do jq empty "$f" || exit 1; done
lane-status-ok
```

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops.
Then validate every manifest from the frozen tree, enforce atomic writes for
manifest/status/dashboard files, accept or reject dirty lane batches one lane at
a time, normalize denominator/mapped/PHP pass-fail/runner/commit fields,
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from that same accepted snapshot, and only then capture one quiesced
root `php tools/run-tests.php` run.
