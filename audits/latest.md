# Independent Audit - 2026-05-24T12:04Z

Scope reviewed: `goal.md`, `progress.md`, current worktree `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
current `lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, and recent Git history through sampled
`7174a5dd Record integration hold status`; commit-time history then advanced
to `edf5d019 Record integration hold status` before this audit commit landed.
I did not edit lane implementation files, launch agents or tmux sessions,
push, read secrets, inspect process environments, credential stores, provider
configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T12:04:02Z, 2026-05-24T12:04:17Z
HEAD: 7174a5dd9802b3520bb53cdb8397309b17084dfd
recent history: 7174a5dd Record integration hold status; ff4d2a66 Refresh independent audit status; 01a02759 Record integration hold status; 0f3196a5 Record integration hold status; 4463211f Refresh independent audit status
post-sample history movement before this audit commit: edf5d019 Record integration hold status
tracked dirty rows: 328 -> 328
default status rows including untracked: 17429 -> 17429
git diff --shortstat: 328 files changed, 229806 insertions(+), 28817 deletions(-) -> 328 files changed, 229813 insertions(+), 28825 deletions(-)
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 11:39:10 UTC with 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
```

Required process-gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T12:04:02Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T12:04:17Z:
no rows

final validation pgrep at 2026-05-24T12:09:05Z:
2356500 php tools/run-tests.php
2357304 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php lanes/syncthing/tests/ProgressEmitterTest.php lanes/syncthing/tests/ProtocolValidationTest.php lanes/syncthing/tests/PullDbUpdaterTest.php lanes/syncthing/tests/PullFinisherTest.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php

owner evidence sampled immediately after final validation pgrep:
PID 2356500 USER claude PPID 2356415 STAT R+ ETIMES 36 COMMAND php tools/run-tests.php
PID 2357304 USER claude PPID 2357208 STAT R+ ETIMES 30 COMMAND php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php lanes/syncthing/tests/ProgressEmitterTest.php lanes/syncthing/tests/ProtocolValidationTest.php lanes/syncthing/tests/PullDbUpdaterTest.php lanes/syncthing/tests/PullFinisherTest.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php
```

I did not start `php tools/run-tests.php`. The exact gate was clear in the
audit samples, but the checkout was still moving during the sampling window
and was not an accepted frozen snapshot; final validation then matched an
active no-argument root harness owned by `claude`, so a duplicate root run was
blocked by the required process gate.

`jq empty` passed for all 12 lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current count sample:

```text
lane          manifest total/mapped          lane-status php   dashboard total/mapped/php
difftastic    1056 / 827                     3230 / 0          735 / 374 / 374
dolt          string total / 613             424 / 0           inventory / 613 / 356
esbuild       2567 / 424                     424 / 0           2567 / 311 / 311
gitoxide      2877 / 2877                    7129 / 0          2877 / 2751 / 5634
libsqlite     1589 / 346                     346 / 0           1589 / 286 / 286
LightningCSS  3546 / 2759                    4043 / 0          3532 / 1732 / 2197
markerPDF     392 / 343                      480 / 0           330 / 280 / 416
pandoc        2276 / 1878                    359 / 0           2276 / 1061 / 278
quadrable     55 / 55                        231 / 0           55 / 55 / 190
rclone        1601 / 901                     901 / 0           1601 / 698 / 698
readability   1984 / 1984                    3511 / 0          1984 / 1984 / 204
syncthing     658 / 658                      7832 / 0          658 / 658 / 4579
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:47`, `audits/integration-status.md:15`,
     `audits/integration-status.md:56`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29` requires small reviewable slices
     with passing tests, and `goal.md:48` requires finished agent work to be
     verified, committed, integrated, and cleaned up.
   - Evidence: after the previous audit commit, sampled `HEAD` advanced to
     `7174a5dd Record integration hold status`, and commit-time history moved
     again to `edf5d019 Record integration hold status`. My two samples kept
     tracked dirty rows at `328` and default status rows at `17429`, but
     shortstat still moved from `229806/28817` to `229813/28825`. The latest
     integration status also says no lane output was integrated and that
     current acceptance remains unsafe.

2. **Critical - there is still no audit-acceptable root PHP result for the
   current snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:47`,
     `audits/integration-status.md:31`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and static checks with failures recorded honestly.
   - Evidence: the required exact process gate returned no rows in the two
     audit samples, but the worktree changed during the same sample window and
     is not an accepted frozen source snapshot. Final validation then matched
     active no-argument root PID `2356500` owned by `claude` plus focused
     Syncthing PID `2357304`, so I did not start a duplicate root harness.

3. **Critical - `porting.html` and `porting-summary.json` are stale and still
   materially overstate status.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:8`,
     `porting-summary.json:216`, `porting-summary.json:218`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     coordination files and a dashboard showing denominator, mapped tests, PHP
     pass/fail, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, source snapshot
     `79768df0c427`, and 22 dependency rows. Current `HEAD` is
     `7174a5dd9802`, and `dependency-backlog.json` has 37 rows.

4. **High - manifest, lane-status, and dashboard counts contradict each other
   across every active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:9`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:44` and `goal.md:45` require
     comparable denominator, mapped-test, PHP pass/fail, blocker, current-work,
     and commit fields.
   - Evidence: Difftastic is manifest `1056/827`, status `3230/0`,
     dashboard `735/374/374`; Gitoxide is manifest `2877/2877`, status
     `7129/0`, dashboard `2877/2751/5634`; markerPDF is manifest `392/343`,
     status `480/0`, dashboard `330/280/416`; Readability is manifest
     `1984/1984`, status `3511/0`, dashboard `1984/1984/204`; Syncthing is
     manifest `658/658`, status `7832/0`, dashboard `658/658/4579`.

5. **High - Dolt still has a prose/string upstream denominator.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2517`, `porting.html:57`,
     `porting-summary.json:28`.
   - Goal requirement at risk: `goal.md:25` requires a real upstream
     benchmark denominator, and `goal.md:45` requires that denominator to be
     visible in the dashboard.
   - Evidence: `benchmarkDenominator.total` is still a long prose string
     while `benchmarkDenominator.mapped` is numeric `613`. The dashboard falls
     back to `inventory`, so percentage and coverage arithmetic cannot be
     audited.

6. **High - support-library coverage remains backlog-only under the latest
   2026-05-24 11:59 UTC directive.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:5`,
     `dependency-backlog.json:81`, `dependency-backlog.json:97`,
     `dependency-backlog.json:113`, `dependency-backlog.json:272`,
     `dependency-backlog.json:413`, `dependency-backlog.json:532`,
     `dependency-backlog.json:629`, `porting.html:75`.
   - Goal requirement at risk: the latest support-library directive requires
     every essential rich-function dependency to have a bounded native PHP
     component, activation gate, dependency-specific upstream/spec denominator,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where
     relevant, and as much upstream/spec-suite evidence as can actually run.
   - Evidence: `dependency-backlog.json` has 37 rows and `0` active support
     ports. Pandoc-related DOC, DOCX/OpenXML, PDF handoff/text extraction,
     EPUB, ODT/OpenDocument, templates, citations, math, tables,
     package/ZIP, XML/HTML, Unicode/charset, YAML metadata, and
     archive/compression rows are accounted for on paper, but none has a
     support-library manifest, accepted PHP component, pass/fail evidence, or
     malformed/corrupt suite result. The same is true for important non-Pandoc
     rows such as Git wire protocol, Quadrable proof transport, WebDAV, QR,
     URL handling, source maps, protobuf, checksum/hash, SQL semantics, and
     archive/compression.

7. **High - lane-local dependency-adjacent work is still not shared
   support-library progress.**
   - Paths: `lanes/gitoxide/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`,
     `lanes/syncthing/lane-status.json`, `dependency-backlog.json:97`,
     `dependency-backlog.json:113`, `dependency-backlog.json:413`,
     `dependency-backlog.json:532`.
   - Goal requirement at risk: support-library work must be reusable,
     activation-gated, dependency-specific, tested, and not a hidden expansion
     of a lane.
   - Evidence: Git wire/protocol, Quadrable proof transport, YAML metadata,
     source maps, WebDAV, URL cleanup, QR generation, and route/static
     support are still lane-local or backlog rows. They should not count as
     support-library progress until a dedicated component has its own
     denominator, fixtures, malformed cases, and PHP pass/fail evidence from
     an accepted snapshot.

8. **High - markerPDF still mixes native PDF behavior with external runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:832`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:986`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1064`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1080`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1085`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid wrappers
     around JS/Rust/Go/C binaries or shell-outs from counting as the main
     deliverable; bridge/oracle tooling may only be temporary.
   - Evidence: the lane includes useful native PDF parsing and metadata
     slices, but the status and manifest still carry Streamlit/FastAPI/Uvicorn,
     OCRmyPDF/Tesseract/Ghostscript, pypdfium/PIL, Texify/Nougat/model-worker,
     Pandoc/XeLaTeX, Poetry/publish, and shell lifecycle planning. Those are
     blockers or caller-supplied/oracle boundaries, not native port progress.

9. **Medium - near-complete percentages continue to overstate accepted
   upstream and root parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:35` through `goal.md:40` say passing
     tests are not enough, upstream tests are the source of truth where
     possible, and hard gaps must be recorded as blockers or future slices.
   - Evidence: lane statuses and the stale dashboard show `92%` to `99%`
     progress while full upstream suites remain unrun, static-only, or bounded
     for Difftastic, esbuild, Gitoxide, SQLite, Pandoc, markerPDF, rclone,
     Dolt, and Syncthing, and root aggregate evidence is still pending for
     this accepted snapshot.

## Next Intervention

Freeze lane writers, dashboard/status publishers, support-library scouts,
focused runners, root runners, and the Dolt runner. Require two stable polls
of `HEAD`, tracked rows, untracked-inclusive rows, shortstat, exact process
gates, dependency/dashboard counts, and relevant log mtimes. Then accept
exactly one coherent lane batch with manifest/status schema normalization,
run focused lane verification plus `git diff --check`, activate only the
support-library rows whose base-lane gate is accepted or truly blocked, add
support-library manifests before counting support progress, regenerate
`porting.html` and `porting-summary.json` from the accepted commit, and run
one serialized no-argument `php tools/run-tests.php` only if the exact process
gate remains empty on that frozen snapshot.
