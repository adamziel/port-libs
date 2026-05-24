# Independent Audit - 2026-05-24T11:56Z

Scope reviewed: `goal.md`, `progress.md`, current worktree `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
current `lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git
history through sampled `0f3196a5 Record integration hold status`; commit-time
history then advanced to `01a02759 Record integration hold status` before this
audit commit landed. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T11:55:22Z, 2026-05-24T11:56:45Z
HEAD: 0f3196a5edfa
recent history: 0f3196a5 Record integration hold status; 4463211f Refresh independent audit status; 429fbac2 Track Pandoc YAML metadata dependency; 43adc52f Record integration hold status
post-sample history movement before this audit commit: 01a02759 Record integration hold status
tracked dirty rows: 328 -> 328
default status rows including untracked: 17297 -> 17354
git diff --shortstat: 328 files changed, 229564 insertions(+), 29606 deletions(-) -> 328 files changed, 229564 insertions(+), 29611 deletions(-)
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 11:39:10 UTC with 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
```

Required process-gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T11:55:22Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T11:56:45Z:
2221228 php tools/run-tests.php
2226055 php tools/run-tests.php lanes/syncthing/tests

owner evidence sampled immediately after pgrep:
PID 2221228 USER claude PPID 2221119 STAT R+ ETIMES 63 COMMAND php tools/run-tests.php
PID 2226055 USER claude PPID 2162252 STAT Rs ETIMES 52 COMMAND php tools/run-tests.php lanes/syncthing/tests
```

I did not start `php tools/run-tests.php`. The checkout is still not an
accepted frozen snapshot, and the final required exact process gate had an
active no-argument root harness owned by `claude`.

`jq empty` passed for all 12 lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current count sample:

```text
lane          manifest total/mapped          lane-status php   dashboard total/mapped/php
difftastic    1046 / 817                     3219 / 0          735 / 374 / 374
dolt          string total / 613             424 / 0           inventory / 613 / 356
esbuild       2567 / 423                     423 / 0           2567 / 311 / 311
gitoxide      2877 / 2877                    7102 / 0          2877 / 2751 / 5634
libsqlite     1589 / 345                     345 / 0           1589 / 286 / 286
LightningCSS  3546 / 2759                    4043 / 0          3532 / 1732 / 2197
markerPDF     392 / 343                      479 / 0           330 / 280 / 416
pandoc        2276 / 1878                    358 / 0           2276 / 1061 / 278
quadrable     55 / 55                        231 / 0           55 / 55 / 190
rclone        1601 / 899                     899 / 0           1601 / 698 / 698
readability   1984 / 1984                    3498 / 0          1984 / 1984 / 204
syncthing     658 / 658                      7805 / 0          658 / 658 / 4579
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:47`, `lanes/*/lane-status.json`, `audits/latest.md`.
   - Goal requirement at risk: `goal.md:29` requires small reviewable slices
     with passing tests, and `goal.md:48` requires finished agent work to be
     verified, committed, integrated, and cleaned up.
   - Evidence: after the prior audit commit, `HEAD` advanced through
     `4463211f` to sampled `0f3196a5`, and then to integration-hold parent
     `01a02759` before this audit commit landed. Recent history remains
     audit/integration/status dominated, tracked dirty rows are still `328`,
     untracked-inclusive rows
     moved `17297 -> 17354`, and shortstat moved while staying at `328 files`
     changed. Lane status files still describe broad uncommitted lane-local
     batches with root verification and commit acceptance deferred to the
     supervisor/integrator.

2. **Critical - there is no audit-acceptable root PHP result for this snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:47`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and static checks with failures recorded honestly.
   - Evidence: the required final process gate matched active no-argument root
     PID `2221228` owned by `claude` plus focused Syncthing PID `2226055`. I
     did not start a duplicate root harness. Earlier in the same audit the gate
     returned no rows, but the tree was still moving and had no accepted frozen
     source snapshot to bind a root result to.

3. **Critical - `porting.html` and `porting-summary.json` are stale and
   materially overstate coordination status.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:2`, `porting-summary.json:3`,
     `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     coordination files and a dashboard showing denominator, mapped tests, PHP
     pass/fail, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, source snapshot
     `79768df0c427`, stale per-lane counts, and 22 dependency rows. Current
     `HEAD` is `0f3196a5edfa`, and `dependency-backlog.json` has 37 rows.

4. **High - manifest, lane-status, and dashboard counts contradict each other
   across every active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:9`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:44` and `goal.md:45` require
     comparable denominator, mapped-test, PHP pass/fail, blocker, current-work,
     and commit fields.
   - Evidence: Difftastic is manifest `1046/817`, status `3219/0`, dashboard
     `735/374/374`; Gitoxide is manifest `2877/2877`, status `7102/0`,
     dashboard `2877/2751/5634`; LightningCSS is manifest `3546/2759`, status
     `4043/0`, dashboard `3532/1732/2197`; markerPDF is manifest `392/343`,
     status `479/0`, dashboard `330/280/416`; Readability is manifest
     `1984/1984`, status `3498/0`, dashboard `1984/1984/204`; Syncthing is
     manifest `658/658`, status `7805/0`, dashboard `658/658/4579`.

5. **High - Dolt still has a prose/string denominator, so its upstream
   denominator is not machine-checkable.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`, `porting.html:57`,
     `porting-summary.json:28`.
   - Goal requirement at risk: `goal.md:25` requires a real upstream
     benchmark denominator, and `goal.md:45` requires that denominator to be
     visible in the dashboard.
   - Evidence: `benchmarkDenominator.total` is still a string while
     `benchmarkDenominator.mapped` is numeric `613`. The dashboard falls back
     to `inventory`, so percentage and coverage arithmetic cannot be audited.

6. **High - near-complete percentages still overstate accepted upstream and
   root parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:35` through `goal.md:40` say passing
     focused tests are not enough, upstream tests are the source of truth where
     possible, and hard gaps must be recorded as blockers or future slices.
   - Evidence: lane statuses show `95` to `99` percent while root verification
     is pending for this accepted snapshot and major upstream suites remain
     unrun, static-only, or bounded: Difftastic full Cargo/tree-sitter parity,
     esbuild `make test-all`, Gitoxide full Cargo workspace, SQLite broader
     all/release permutations, Pandoc Haskell runner parity, markerPDF full
     model/runtime benchmarks, rclone provider/mount suites, and Syncthing
     full `go test ./...`.

7. **High - support-library coverage remains backlog-only despite expanding
   rich-function scope.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:5`,
     `dependency-backlog.json:45`, `dependency-backlog.json:61`,
     `porting.html:75`, `porting-summary.json:340`.
   - Goal requirement at risk: this audit run requires support libraries to
     have a bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, and as much upstream/spec suite as
     can honestly run.
   - Evidence: `dependency-backlog.json` has 37 rows and `0` active support
     ports, while the dashboard and summary still show 22 rows. New rows such
     as `git-wire-protocol-core`, `quadrable-proof-transport-codec-core`, and
     `yaml-metadata-core` are scoped on paper but have no support-library
     manifests, accepted native components, pass/fail evidence, or malformed
     case coverage.

8. **High - lane-local dependency-adjacent work is still not shared
   support-library progress.**
   - Paths: `lanes/gitoxide/lane-status.json`,
     `lanes/esbuild/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`,
     `dependency-backlog.json:55`, `dependency-backlog.json:75`,
     `dependency-backlog.json:107`.
   - Goal requirement at risk: support-library progress must be bounded,
     activation-gated, dependency-specific, tested, and not a hidden expansion
     of a lane.
   - Evidence: Git wire/protocol, esbuild source maps/package-resolution
     adjacent work, rclone WebDAV/accounting, Readability URL cleanup,
     Syncthing QR/auth/static route work, and Quadrable proof transport remain
     lane-local or pending. They should not count as shared support progress
     until a dedicated support component has its own manifest, fixtures,
     malformed cases, and PHP pass/fail evidence from an accepted snapshot.

9. **High - markerPDF still mixes native PDF behavior with external runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`, `progress.md:32`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid wrappers
     around JS/Rust/Go/C binaries or shell-outs from counting as the main
     deliverable; bridge/oracle tooling may only be temporary.
   - Evidence: the lane includes useful native PDF parsing and metadata
     slices, but its status still carries Streamlit/FastAPI/Uvicorn,
     OCRmyPDF/Tesseract/Ghostscript, pypdfium/PIL, Texify/Nougat/model worker,
     Pandoc/XeLaTeX, publish/build, and shell lifecycle boundaries. Those are
     blockers or integration notes, not native port progress.

## Next Intervention

Freeze all lane writers, dashboard/status publishers, and root/focused test
runners. Require two stable polls of `HEAD`, tracked rows,
untracked-inclusive rows, shortstat, exact process gates,
dependency/dashboard counts, and relevant log mtimes. Then accept exactly one
coherent lane batch with schema/count normalization, run focused verification
plus `git diff --check`, run one serialized no-argument
`php tools/run-tests.php` only if the exact process gate stays empty,
regenerate `porting.html` and `porting-summary.json` from the accepted commit,
and only then commit or reject that batch.
