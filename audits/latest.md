# Independent Audit - 2026-05-23T17:16:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, recent Git history, current worktree
state, process state, and the required duplicate-root test gate. I also sampled
`lanes/*/lane-status.json` and `porting-summary.json` for dashboard/status
alignment.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, copied fixture oracles, generated artifacts, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

Sampled `HEAD`: `580a7d56baac` (`Refresh independent audit status`). The
latest 44 commits before the nearest sampled implementation commit are
audit-only `Refresh independent audit status` commits. The nearest recent
non-audit commit sampled was `b75226d1` (`Port rclone OneDrive Object.Update
upload selection`).

## Manifest/Status Snapshot

All lane manifests and sampled lane-status files parsed at the final audit
sample. During the audit, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`
briefly failed `jq empty` with a parse error at line 595 before an active writer
rewrote it into valid JSON.

| Lane | Current manifest denominator | Manifest mapped | Lane PHP pass/fail | Latest commit field |
| --- | ---: | ---: | ---: | --- |
| difftastic | 615 inspected artifacts | 310 | 310 / 0 | pending in shared dirty worktree |
| dolt | prose field embeds 613 upstream executable test files | 593 | 317 / 0 | not committed |
| esbuild | 2,567 upstream entry points | 264 | 264 / 0 | uncommitted lane batch |
| gitoxide | 2,877 upstream files | 2,170 | 4,124 / 0 | pending |
| libsqlite | 1,589 upstream units | 253 | 253 / 0 | uncommitted lane-scoped changes |
| lightningcss | 3,532 behavior checks | 1,440 | 1,629 / 0 | uncommitted shared worktree batch |
| markerPDF | 295 static behavior/reference units | 243 | 373 / 0 | uncommitted lane batch |
| pandoc | 2,276 inspected artifacts | 799 | 239 / 0 | pending |
| quadrable | 55 tracked paths plus runner notes | 55 | 164 / 0 | pending lane batch |
| rclone | 1,601 Go test/benchmark/example units | 543 | 543 / 0 | pending lane-local changes |
| readability | 1,984 Mocha tests | 1,913 | 170 / 0 | uncommitted Herald Sun slice |
| syncthing | 658 Go entry points | 454 | 3,183 / 0 | pending lane-local slice |

## Findings

1. **Critical - source-of-truth manifests are being rewritten while the audit is reading them.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:590`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:592`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:595`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:598`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:44`, `goal.md:45`, and
     `goal.md:52` require every lane to have a defensible
     `UPSTREAM_TEST_MANIFEST.json` that can feed progress tracking and the
     generated dashboard.
   - Evidence: an audit sample of
     `jq empty lanes/difftastic/UPSTREAM_TEST_MANIFEST.json` failed with
     `Expected another key-value pair at line 595, column 5`; line 595 was a
     trailing comma inside `nativeImplementation` after
     `"shellOutsAllowedForProgress": false`.
   - Evidence: a later sample of the same file parsed successfully and now
     shows a rewritten `currentSlice` at line 595 and `310 / 615` counts at
     lines 14, 15, 590, and 599. That means the manifest changed during this
     audit.
   - Evidence: current manifest values also changed during sampling outside
     Difftastic: LightningCSS moved to `1,440 / 3,532`, and Readability moved
     to `1,913 / 1,984`.
   - Impact: even when the final files parse, the audit cannot treat a manifest
     or dashboard snapshot as authoritative while active writers are modifying
     source-of-truth status files mid-read. Atomic writes and a frozen tree are
     prerequisites for the next accepted integration checkpoint.

2. **Critical - there is still no stable integration snapshot suitable for an accepted root-test baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `.tmux-team/prompts/*`, `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:20` requires capped supervised lane
     work and one auditor; `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable commits, verification, progress updates, and
     honest repo-wide test evidence.
   - Evidence: `progress.md:25` still documents a target of two implementation
     lanes plus one auditor, and `progress.md:31` through `progress.md:42`
     reports all lane sessions as `stopped`.
   - Evidence: process sampling found active dashboard updater, team watchdog,
     evaluator, capacity controller, auditor, integrator, Dolt runner, and
     lane-agent watchdogs for esbuild, syncthing, quadrable, rclone, pandoc,
     difftastic, readability, libsqlite, lightningcss, and dolt.
   - Evidence: the worktree is not quiescent. Latest samples reported `2410`
     total status rows including untracked files, `218` tracked dirty rows, and
     `220 files changed, 97494 insertions(+), 8867 deletions(-)`.
   - Evidence: the exact duplicate-root gate returned no rows at the main
     sample, but the tree was still unstable enough that a no-argument root run
     would not be trustworthy.

3. **Critical - `porting.html` and `porting-summary.json` remain stale and do not satisfy the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:65`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52`
     require a generated dashboard with current average progress and separate
     per-lane upstream denominator, mapped tests, PHP pass/fail, phase, audit,
     blocker, and commit fields.
   - Evidence: `porting.html:32` says generated `2026-05-23 04:57:16 UTC`,
     and `porting.html:33` through `porting.html:36` still publish snapshot
     `bda83c6b93d4`, while sampled `HEAD` is `580a7d56baac`.
   - Evidence: published rows disagree with current manifest/status data:
     Difftastic is published as `160 / 417` but current is `310 / 615`; Dolt is
     `242 / 613` but current mapped is `593`; LightningCSS is `773 / 3532` but
     current is `1440 / 3532`; markerPDF is `159 / 78` but current is
     `243 / 295`; rclone is `291 / 327` but current is `543 / 1601`;
     Readability is `1031 / 1984` but current is `1913 / 1984`; Syncthing is
     `235 / 658` but current mapped is `454 / 658`.
   - Evidence: `porting.html:41` through `porting.html:50` still lacks a
     separate upstream-denominator column and a separate PHP pass/fail column;
     rows at `porting.html:54` through `porting.html:65` mix PHP pass/fail and
     mapped coverage in one cell.

4. **High - `progress.md` still describes a stopped system while active writers keep changing the repo.**
   - Paths: `progress.md:14`, `progress.md:25`, `progress.md:31` through
     `progress.md:42`, `progress.md:363`, `progress.md:367`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to include
     active lanes, current owner/session, next task per lane, percentage
     estimates, latest commit, and blockers.
   - Evidence: active repo processes contradict the stopped-session table.
   - Evidence: `progress.md:14` still leaves the independent auditor loop
     unchecked even though audit refresh commits and a `port-auditor` watchdog
     are active.
   - Evidence: recent progress entries correctly warn about stale dashboard
     data and dirty batches, but the active system still permits source-of-truth
     manifests to be briefly invalid or to change during audit sampling.

5. **High - most lane progress remains unaccepted dirty-batch work rather than small committed slices.**
   - Paths: `lanes/*/lane-status.json`, recent Git history,
     `progress.md:44` through `progress.md:50`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable commits and verification.
   - Evidence: every sampled lane-status `latestCommit` field says pending,
     uncommitted, not committed, or current dirty work instead of a reviewable
     accepted commit.
   - Evidence: the latest 44 commits before the nearest sampled implementation
     commit are audit-only refreshes touching only `audits/latest.md` and
     `progress.md`.
   - Evidence: with 220 tracked dirty files and status files changing during
     sampling, the current lane slices cannot be treated as accepted integration
     progress.

6. **High - manifest/status count schemas remain non-normalized and internally inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2121`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/gitoxide/lane-status.json:6`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:1908`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:648`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:654`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1160`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:45`
     require real upstream denominators, mapped upstream tests, PHP pass/fail
     counts, and comparable dashboard fields.
   - Evidence: denominator `total` is numeric in some manifests and long prose
     in others. Dolt's `total` is a long narrative field that embeds multiple
     incompatible denominators before noting the `613` executable-test-file
     count.
   - Evidence: PHP pass units are not comparable with mapped upstream units:
     Gitoxide maps `2,170` upstream units but reports `4,124` PHP assertions;
     Syncthing maps `454` but reports `3,183`; Readability maps `1,913` but
     reports `170`; markerPDF maps `243` but reports `373`; LightningCSS maps
     `1,440` and reports `1,629` local assertions.
   - Evidence: Syncthing's manifest warning still says `453` focused lane
     checks at `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1160`, while the
     manifest header now says `454` at line 15.

7. **High - blocker language still understates full upstream parity gaps.**
   - Paths: `lanes/gitoxide/lane-status.json:12`,
     `lanes/dolt/lane-status.json:12`, `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/quadrable/lane-status.json:12`,
     `lanes/lightningcss/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and hard features to be marked as blockers or future slices.
   - Evidence: several blockers begin with "No current ..." or "none" while
     also admitting major unexecuted or pending work: full cargo workspace,
     full Dolt parity, markerPDF live Python/model workflows, rclone provider
     and mount parity, full Syncthing `go test ./...`, full Pandoc Haskell
     runner, Quadrable full fuzzer/benchmark runs, LightningCSS root aggregate,
     and esbuild release-extra `make test-all`.
   - Audit judgment: "no blocker" is defensible only for the latest focused
     local slice. As written, it is too easy for the dashboard to imply lane
     parity that has not been demonstrated.

8. **Medium - progress accounting still blends static/copied oracle coverage with native implementation parity.**
   - Paths: `lanes/readability/UPSTREAM_TEST_MANIFEST.json:648`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:545`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:590`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1160`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:32`.
   - Goal requirement at risk: `goal.md:30` and `goal.md:35` say generated
     fixtures, bridge calls, shell-outs, and shallow fixture parity do not count
     as native implementation progress by themselves.
   - Evidence: Readability maps `1,913` upstream Mocha checks while native PHP
     behavior tests are `170`, and the manifest correctly warns this is not full
     native PHP parity.
   - Evidence: markerPDF maps `243 / 295` static/source/helper-script units,
     but the warning still says live upstream conversion depends on the Python,
     PDF, model, OCR, Streamlit, FastAPI, and packaging stack.
   - Evidence: Difftastic, Syncthing, and Pandoc explicitly warn that current
     denominators are static or bounded-focused inventories, not full upstream
     runner parity.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Main sample result: no rows.

No root harness was started because the tree was not stable enough: active
writers/status loops persisted, the worktree was heavily dirty, and a required
manifest was observed changing from invalid JSON to valid JSON during the audit.

## Next Intervention

Freeze active writers/status publishers and duplicate focused/root test loops.
Then validate all manifests from the frozen tree, enforce atomic writes for
status/manifest updates, accept or reject dirty lane batches one lane at a time,
normalize denominator/mapped/PHP pass-fail/runner and commit fields, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
that same accepted snapshot, and only then capture one quiesced root
`php tools/run-tests.php` run.
