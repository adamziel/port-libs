# Independent Audit - 2026-05-24T11:37Z

Scope reviewed: `goal.md`, `progress.md`, current worktree `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, and recent Git history through
`120fd98a Refresh independent audit status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T11:36:33Z, 2026-05-24T11:37:14Z
HEAD: 120fd98a638a
recent history: 120fd98a Refresh independent audit status; 1edf5b74 Record integration hold status; 442c9358 Refresh independent audit status; 8c9a638a Record integration hold status; e4fc1f56 Record integration hold status
tracked dirty rows: 329 -> 330
default status rows including untracked: 17107 -> 17165
git diff --shortstat: 329 files changed, 227255 insertions(+), 29623 deletions(-) -> 330 files changed, 227432 insertions(+), 29618 deletions(-)
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 11:14:00 UTC with 36 rows (1 blocked, 24 candidate, 11 deferred, 0 active)
root run by this audit: not started
```

Required pre-root process-gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T11:36:33Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T11:37:14Z:
1998375 php tools/run-tests.php
1999072 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php lanes/syncthing/tests/BepConnectionDiagnosticsTest.php lanes/syncthing/tests/BepConnectionLifecycleTest.php lanes/syncthing/tests/BepFrameStreamTest.php lanes/syncthing/tests/BepKeepaliveTest.php lanes/syncthing/tests/BepSessionTest.php lanes/syncthing/tests/BepWireTest.php lanes/syncthing/tests/BlockDiffTest.php lanes/syncthing/tests/BlockListTest.php lanes/syncthing/tests/BlockPullReordererTest.php lanes/syncthing/tests/ClusterConfigAutoAcceptorTest.php lanes/syncthing/tests/ClusterConfigIntroducerTest.php lanes/syncthing/tests/ClusterPendingTest.php lanes/syncthing/tests/ConfigDefaultDeviceTest.php lanes/syncthing/tests/ConfigDefaultFolderTest.php lanes/syncthing/tests/ConfigDefaultIgnoresTest.php
1999513 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php lanes/syncthing/tests/ProgressEmitterTest.php lanes/syncthing/tests/ProtocolValidationTest.php lanes/syncthing/tests/PullDbUpdaterTest.php lanes/syncthing/tests/PullFinisherTest.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php
2000157 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests

owner evidence sampled immediately after pgrep:
PID 1998375 USER claude PPID 1998329 STAT R+ ETIMES 26 COMMAND php tools/run-tests.php
PID 1999513 USER claude PPID 1999384 STAT R+ ETIMES 21 COMMAND php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php lanes/syncthing/tests/ProgressEmitterTest.php lanes/syncthing/tests/ProtocolValidationTest.php lanes/syncthing/tests/PullDbUpdaterTest.php lanes/syncthing/tests/PullFinisherTest.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php
```

I did not start `php tools/run-tests.php`. The process gate was clear in the
first sample but the checkout was already moving; the next sample found an
active no-argument root harness plus focused lane harnesses. `jq empty` passed
for all 12 lane manifests, all 12 lane-status files, `porting-summary.json`,
and `dependency-backlog.json`.

Current count sample:

```text
lane          manifest total/mapped          lane-status php   dashboard total/mapped/php
difftastic    1036 / 807                     3199 / 0          735 / 374 / 374
dolt          string total / 613             423 / 0           inventory / 613 / 356
esbuild       2567 / 419                     419 / 0           2567 / 311 / 311
gitoxide      2877 / 2877                    7064 / 0          2877 / 2751 / 5634
libsqlite     1589 / 344                     344 / 0           1589 / 286 / 286
LightningCSS  3546 / 2756                    4033 / 0          3532 / 1732 / 2197
markerPDF     390 / 341                      477 / 0           330 / 280 / 416
pandoc        2276 / 1848                    356 / 0           2276 / 1061 / 278
quadrable     55 / 55                        230 / 0           55 / 55 / 190
rclone        1601 / 895                     895 / 0           1601 / 698 / 698
readability   1984 / 1984                    258 / 0           1984 / 1984 / 204
syncthing     658 / 658                      7743 / 0          658 / 658 / 4579
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:47`, `audits/integration-status.md:3`,
     `lanes/*/lane-status.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:29` requires small reviewable
     committed slices with passing tests, and `goal.md:48` requires finished
     agent work to be verified, committed, integrated, and cleaned up.
   - Evidence: current history remains status/audit dominated, while tracked
     dirty rows moved `329 -> 330`, untracked-inclusive rows moved
     `17107 -> 17165`, and shortstat moved from `329 files changed, 227255
     insertions(+), 29623 deletions(-)` to `330 files changed, 227432
     insertions(+), 29618 deletions(-)` during this audit. Every lane status
     still reports pending/uncommitted work.

2. **Critical - there is still no audit-acceptable no-argument repo-wide PHP
   result for this snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:47`,
     `audits/integration-status.md:22`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and static checks with failures recorded honestly.
   - Evidence: the required exact pre-root gate was clear at 11:36:33 UTC but
     the tree was moving. At 11:37:14 UTC it matched active no-argument root
     PID `1998375` owned by `claude` (`php tools/run-tests.php`, PPID
     `1998329`, state `R+`, elapsed `26s` at owner sample), plus focused
     Syncthing/rclone harnesses. I did not start a duplicate. Any result from
     that external run still has to be tied to a frozen accepted source
     snapshot before it can clear the goal requirement.

3. **Critical - `porting.html` and `porting-summary.json` are stale and
   overstate current status.**
   - Paths: `porting.html:32`, `porting.html:34`,
     `porting.html:35`, `porting.html:56`, `porting.html:75`,
     `porting-summary.json:1`, `porting-summary.json:2`,
     `dependency-backlog.json:3`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     coordination files and a dashboard showing denominator, mapped tests, PHP
     pass/fail, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, source snapshot
     `79768df0c427`, old lane counts, and 22 dependency rows. Current `HEAD`
     is `120fd98a638a`, and the backlog has 36 rows.

4. **High - manifest, lane-status, and dashboard counts are contradictory
   across every active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/*/lane-status.json:4`.
   - Goal requirement at risk: `goal.md:44` and `goal.md:45` require
     comparable denominator, mapped-test, PHP pass/fail, blocker, current-work,
     and commit fields.
   - Evidence: Difftastic is manifest `1036/807`, status `3199/0`, dashboard
     `735/374/374`; LightningCSS is manifest `3546/2756`, status `4033/0`,
     dashboard `3532/1732/2197`; markerPDF is manifest `390/341`, status
     `477/0`, dashboard `330/280/416`; Syncthing is manifest `658/658`,
     status `7743/0`, dashboard `658/658/4579`.

5. **High - Dolt still has a non-machine-checkable denominator.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2514`,
     `porting.html:57`, `porting-summary.json:25`.
   - Goal requirement at risk: `goal.md:25` requires a real upstream
     benchmark denominator to be mapped, and `goal.md:45` requires that
     denominator to be visible in the dashboard.
   - Evidence: `benchmarkDenominator.mapped` is numeric `613`, but
     `benchmarkDenominator.total` is a prose evidence paragraph at line 2514
     instead of a numeric denominator. The dashboard falls back to
     `inventory`, so denominator arithmetic cannot be validated mechanically.

6. **High - near-complete percentages overstate accepted upstream and root
   parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/lane-status.json:4`,
     `lanes/esbuild/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:4`,
     `lanes/libsqlite/lane-status.json:4`,
     `lanes/lightningcss/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:4`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:4`.
   - Goal requirement at risk: `goal.md:35` through `goal.md:40` say passing
     focused tests are not enough, upstream tests are the source of truth where
     possible, and hard gaps must be blockers or future slices.
   - Evidence: dashboard/status rows still show 95-99 percent while the
     accepted root result is pending for the current moving tree and major
     upstream suites remain unrun or static-only, including Difftastic full
     Cargo parity, esbuild `make test-all`, Gitoxide full Cargo workspace,
     SQLite broader `all`/`release` permutations, Pandoc full Haskell runner,
     markerPDF full model/runtime benchmarks, rclone provider/mount parity,
     and Syncthing full `go test ./...`.

7. **High - essential optional-library coverage remains backlog-only, while
   dependency-adjacent behavior continues to grow inside lanes.**
   - Paths: `progress.md:17`, `progress.md:31`,
     `dependency-backlog.json:4`, `dependency-backlog.json:7`,
     `dependency-backlog.json:25`, `dependency-backlog.json:45`,
     `dependency-backlog.json:61`, `porting.html:75`.
   - Goal requirement at risk: this audit run requires support libraries to
     have a bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, and as much upstream/spec suite
     evidence as can honestly run.
   - Evidence: the backlog has 36 rows, but `0` active support-library
     manifests. Rich-function gaps remain for ZIP/package containers,
     XML/HTML, WebDAV, URL percent encoding, Unicode/charset, JSON/JSON5,
     source maps, package resolution, tree-sitter grammar subsets,
     sequence diff/merge, protobuf, checksum/hash, SQL expression semantics,
     archive/compression, QR matrices, and MySQL wire protocol. Lane-local
     Readability URL cleanup, rclone WebDAV, Syncthing QR/auth, esbuild source
     maps, and markerPDF ZIP/PDF/layout/runtime planning should not count as
     shared support-library progress until a gated support component has its
     own denominator and PHP evidence.

8. **High - markerPDF still mixes native PDF evidence with external runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:827`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:828`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1060`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1070`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1076`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1081`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1125`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid wrappers
     around JS/Rust/Go/C binaries or shell-outs from counting as the main
     deliverable, and allow bridge/oracle tooling only temporarily.
   - Evidence: the manifest correctly sets `shellOutsAllowedForProgress` to
     false, but the current slice and mapped behavior list still includes
     Pandoc/XeLaTeX command planning, `chunk_convert.py` shell lifecycle,
     Streamlit command planning, OCRmyPDF/Tesseract/Ghostscript install
     planning, and model/runtime preflight material alongside native PDF
     parsing. Those plans may be useful integration notes, but they must stay
     excluded from native progress unless backed by a bounded native component
     and dependency-specific test evidence.

## Next Intervention

Freeze all lane writers, dashboard/status publishers, and root/focused test
runners. Require two stable polls of `HEAD`, tracked rows, untracked-inclusive
rows, shortstat, exact process gates, dependency/dashboard counts, and relevant
log mtimes. Then accept exactly one coherent lane batch with schema/count
normalization, run focused verification plus `git diff --check`, run one
serialized no-argument `php tools/run-tests.php` only if the exact process gate
stays empty, regenerate `porting.html`/`porting-summary.json` from the accepted
commit, and only then commit or reject that batch.
