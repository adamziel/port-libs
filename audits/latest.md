# Independent Audit - 2026-05-24T11:51Z

Scope reviewed: `goal.md`, `progress.md`, current worktree `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
current `lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git
history through `429fbac2 Track Pandoc YAML metadata dependency`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T11:43:35Z, 2026-05-24T11:44:25Z, 2026-05-24T11:47:44Z, 2026-05-24T11:49:13Z
HEAD: feea2de30f39 -> 1fa8f8907327 -> 43adc52f2cc0 -> 429fbac2fd89
recent history at final sample: 429fbac2 Track Pandoc YAML metadata dependency; 43adc52f Record integration hold status; 1fa8f890 Record integration hold status; feea2de3 Refresh independent audit status
tracked dirty rows: 330 -> 329 -> 331 -> 330
default status rows including untracked: 17224 -> 17227 -> 17233 -> 17235
git diff --shortstat: 330 files changed, 227761 insertions(+), 29594 deletions(-) -> 329 files changed, 228050 insertions(+), 29603 deletions(-) -> 331 files changed, 228313 insertions(+), 29725 deletions(-) -> 330 files changed, 228762 insertions(+), 29711 deletions(-)
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 11:39:10 UTC with 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
```

Required pre-root process-gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T11:43:35Z:
2105548 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php lanes/syncthing/tests/ProgressEmitterTest.php lanes/syncthing/tests/ProtocolValidationTest.php lanes/syncthing/tests/PullDbUpdaterTest.php lanes/syncthing/tests/PullFinisherTest.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php

owner evidence sampled immediately after pgrep:
PID 2105548 USER claude PPID 2105443 STAT R+ ETIMES 41 COMMAND php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php lanes/syncthing/tests/ProgressEmitterTest.php lanes/syncthing/tests/ProtocolValidationTest.php lanes/syncthing/tests/PullDbUpdaterTest.php lanes/syncthing/tests/PullFinisherTest.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T11:44:25Z:
2113946 php tools/run-tests.php lanes/markerpdf/tests/HeadingCleanerTest.php

owner evidence for PID 2113946:
process exited before owner sampling

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T11:47:44Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T11:49:13Z:
no rows

pre-commit pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T11:50:24Z:
2150917 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php lanes/syncthing/tests/ProgressEmitterTest.php lanes/syncthing/tests/ProtocolValidationTest.php lanes/syncthing/tests/PullDbUpdaterTest.php lanes/syncthing/tests/PullFinisherTest.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php

owner evidence sampled immediately after pre-commit pgrep:
PID 2150917 USER claude PPID 2150800 STAT R+ ETIMES 41 COMMAND php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php lanes/syncthing/tests/ProgressEmitterTest.php lanes/syncthing/tests/ProtocolValidationTest.php lanes/syncthing/tests/PullDbUpdaterTest.php lanes/syncthing/tests/PullFinisherTest.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php

post-commit validation pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T11:51:37Z:
2163336 php tools/run-tests.php

owner evidence sampled immediately after post-commit validation:
PID 2163336 USER claude PPID 2149176 STAT Rs ETIMES 16 COMMAND php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. The checkout moved during sampling
and the exact test-process gate was occupied by focused lane harnesses before
and after the final clear sample; post-commit validation then found active
no-argument root PID `2163336` owned by `claude`. `jq empty` passed for all 12 lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current count sample:

```text
lane          manifest total/mapped          lane-status php   dashboard total/mapped/php
difftastic    1046 / 817                     3219 / 0          735 / 374 / 374
dolt          string total / 613             423 / 0           inventory / 613 / 356
esbuild       2567 / 423                     421 / 0           2567 / 311 / 311
gitoxide      2877 / 2877                    7068 / 0          2877 / 2751 / 5634
libsqlite     1589 / 345                     345 / 0           1589 / 286 / 286
LightningCSS  3546 / 2756                    4036 / 0          3532 / 1732 / 2197
markerPDF     391 / 342                      479 / 0           330 / 280 / 416
pandoc        2276 / 1872                    358 / 0           2276 / 1061 / 278
quadrable     55 / 55                        230 / 0           55 / 55 / 190
rclone        1601 / 897                     897 / 0           1601 / 698 / 698
readability   1984 / 1984                    258 / 0           1984 / 1984 / 204
syncthing     658 / 658                      7743 / 0          658 / 658 / 4579
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:47`, `lanes/*/lane-status.json:13`,
     `audits/latest.md`.
   - Goal requirement at risk: `goal.md:29` requires small reviewable slices
     with passing tests, and `goal.md:48` requires finished agent work to be
     verified, committed, integrated, and cleaned up.
   - Evidence: `HEAD` moved from `feea2de30f39` through `1fa8f8907327` and
     `43adc52f2cc0` to `429fbac2fd89` during this audit. Tracked dirty rows
     moved `330 -> 329 -> 331 -> 330`, untracked-inclusive rows moved
     `17224 -> 17227 -> 17233 -> 17235`, and shortstat moved from `330 files
     changed, 227761 insertions(+), 29594 deletions(-)` to `330 files
     changed, 228762 insertions(+), 29711 deletions(-)`. Every lane status still reports
     `pending`, `uncommitted`, or supervisor-owned root/commit acceptance.

2. **Critical - there is still no audit-acceptable no-argument repo-wide PHP
   result for this snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:47`,
     `lanes/*/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and static checks with failures recorded honestly.
   - Evidence: I did not start a duplicate root harness. The process gate
     matched focused Syncthing PID `2105548` owned by `claude`, then focused
     markerPDF PID `2113946` before that process exited. A final gate sample
     returned no rows, but the tree had moved again; post-commit validation
     then found active no-argument root PID `2163336` owned by `claude`.
     Starting a second root run would duplicate active root verification and
     would not be tied to a frozen accepted source snapshot.

3. **Critical - `porting.html` and `porting-summary.json` are stale and
   materially overstate current coordination status.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:56`, `porting.html:75`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     coordination files and a dashboard showing denominator, mapped tests, PHP
     pass/fail, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, source snapshot
     `79768df0c427`, stale lane counts, and 22 dependency rows. Current
     `HEAD` is `429fbac2fd89`, and `dependency-backlog.json` has 37 rows.

4. **High - manifest, lane-status, and dashboard counts contradict each other
   across every active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/*/lane-status.json:4`, `lanes/*/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:44` and `goal.md:45` require
     comparable denominator, mapped-test, PHP pass/fail, blocker, current-work,
     and commit fields.
   - Evidence: Difftastic is manifest `1046/817`, status `3219/0`, dashboard
     `735/374/374`; LightningCSS is manifest `3546/2756`, status `4036/0`,
     dashboard `3532/1732/2197`; markerPDF is manifest `390/341`, status
     `479/0`, dashboard `330/280/416`; rclone is manifest `1601/897`, status
     `897/0`, dashboard `1601/698/698`; Syncthing is manifest `658/658`,
     status `7743/0`, dashboard `658/658/4579`.

5. **High - Dolt still has a prose/string denominator, so the upstream
   denominator is not machine-checkable.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2514`, `porting.html:57`.
   - Goal requirement at risk: `goal.md:25` requires a real upstream
     benchmark denominator to be mapped, and `goal.md:45` requires that
     denominator to be visible in the dashboard.
   - Evidence: `benchmarkDenominator.mapped` is numeric `613`, but
     `benchmarkDenominator.total` is the latest OCT evidence paragraph at line
     2514 instead of a numeric denominator. The dashboard falls back to
     `inventory`, so percentage and coverage arithmetic cannot be validated.

6. **High - near-complete percentages overstate accepted upstream and root
   parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json:4`, `lanes/*/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35` through `goal.md:40` say passing
     focused tests are not enough, upstream tests are the source of truth where
     possible, and hard gaps must be recorded as blockers or future slices.
   - Evidence: status rows still claim `95` to `99` percent while the current
     accepted root result is absent and major upstream suites remain unrun,
     static-only, or bounded: Difftastic full Cargo/tree-sitter parity, esbuild
     `make test-all`, Gitoxide full Cargo workspace, SQLite broader
     all/release permutations, Pandoc Haskell runner parity, markerPDF full
     model/runtime benchmarks, rclone provider/mount suites, and Syncthing
     full `go test ./...`.

7. **High - support-library coverage remains backlog-only despite expanding
   rich-function scope.**
   - Paths: `progress.md:17`, `progress.md:31`,
     `dependency-backlog.json:3`, `dependency-backlog.json:5`,
     `dependency-backlog.json:7`, `dependency-backlog.json:97`,
     `dependency-backlog.json:113`, `porting.html:75`.
   - Goal requirement at risk: this audit run requires support libraries to
     have a bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, and as much upstream/spec suite as
     can honestly run.
   - Evidence: `dependency-backlog.json` has 37 rows and `0` active support
     ports, while the dashboard still shows 22 rows. Newer high-priority rows
     such as `git-wire-protocol-core` and
     `quadrable-proof-transport-codec-core` are well scoped on paper but have
     no support-library manifests, pass/fail evidence, or activated component.
     Rich-function gaps remain for ZIP/package containers, XML/HTML, WebDAV,
     URL percent encoding, YAML/JSON/JSON5 metadata, source maps, package
     resolution, tree-sitter grammar subsets, sequence diff/merge, protobuf,
     checksums, SQL expression semantics, QR matrices, Git wire protocol, and
     Quadrable proof transport.

8. **High - lane-local dependency-adjacent work is still being reported before
   shared support-library acceptance.**
   - Paths: `lanes/rclone/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:12`,
     `dependency-backlog.json:55`, `dependency-backlog.json:75`,
     `dependency-backlog.json:107`.
   - Goal requirement at risk: support-library progress must be bounded,
     activation-gated, dependency-specific, tested, and not a hidden expansion
     of a lane.
   - Evidence: rclone WebDAV, esbuild source maps/package-resolution adjacent
     work, Syncthing QR/auth/static route work, Gitoxide wire/protocol work,
     Readability URL cleanup, and Quadrable proof transport evidence remain
     lane-local/pending. They should not count as shared support progress until
     a dedicated support component has its own manifest, fixtures, malformed
     cases, and PHP pass/fail evidence from an accepted frozen snapshot.

9. **High - markerPDF still mixes native PDF behavior with external runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:527`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1060`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1070`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1076`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1081`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid wrappers
     around JS/Rust/Go/C binaries or shell-outs from counting as the main
     deliverable; bridge/oracle tooling may only be temporary.
   - Evidence: the manifest and status include useful native PDF parsing
     slices, but also Pandoc/XeLaTeX command planning, `chunk_convert.py`
     shell lifecycle, Streamlit command planning, FastAPI/Uvicorn server
     planning, OCRmyPDF/Tesseract/Ghostscript install planning, and model
     runtime preflight material. Those remain integration notes only unless
     separated from progress credit and backed by bounded native components.

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
