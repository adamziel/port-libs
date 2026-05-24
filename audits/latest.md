# Independent Audit - 2026-05-24T08:22Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git
history. I did not edit lane implementation files, launch agents or tmux
sessions, push, read secrets, inspect process environments, credential stores,
provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T08:18Z through 2026-05-24T08:25Z
HEAD observed during audit: 5090aee1 -> 1195512adc0e
recent history: 1195512a Record integration hold status; 5090aee1 Refresh independent audit status; 1cdadbf9 Record integration hold status
branch divergence: main...origin/main [ahead 775, behind 68]
tracked dirty rows: 322
default status rows: 16049
git diff --shortstat: 322 files changed, 202122 insertions(+), 30187 deletions(-)
manifest/status JSON validation: jq empty passed for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dashboard snapshot: porting.html and porting-summary.json still generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 08:10:35 UTC with 24 rows; dashboard still shows 22
root run by this audit: not started
```

Required pre-root process-gate evidence:

```text
2026-05-24T08:18Z pgrep -af '^php tools/run-tests\.php( |$)':
3870942 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterTest.php ... lanes/syncthing/tests/SentDownloadStateTest.php

2026-05-24T08:21Z ps -o pid,user,ppid,stat,etime,args -p 3870942:
process had exited before owner sampling; only the ps header returned

2026-05-24T08:21Z later pgrep -af '^php tools/run-tests\.php( |$)':
no rows

2026-05-24T08:24Z pre-commit pgrep -af '^php tools/run-tests\.php( |$)':
3943109 php tools/run-tests.php
3945143 php tools/run-tests.php lanes/quadrable/tests
3945496 php tools/run-tests.php lanes/readability/tests
3945578 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ... ConfigDefaultIgnoresTest.php
3945622 php tools/run-tests.php lanes/syncthing/tests/ConfigDevicesTest.php ... FileInfoEquivalenceTest.php

2026-05-24T08:25Z owner sample:
3943109 claude 3942868 R+ 00:19 php tools/run-tests.php
3945578 claude 3945432 R+ 00:14 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ... ConfigDefaultIgnoresTest.php
```

I did not start `php tools/run-tests.php`. The initial exact process gate
matched an active PHP harness, and after it cleared the checkout had still moved
and remained a broad dirty aggregate. The pre-commit gate then matched an active
no-argument root harness owned by `claude`, so a duplicate audit-owned root run
was forbidden.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:39`, `lanes/*/lane-status.json:13`, recent Git
     history.
   - Requirement at risk: `goal.md:29` requires small reviewable slices with
     passing tests; `goal.md:48` requires finished agent output to be verified,
     committed, and integrated cleanly.
   - Evidence: this audit saw `HEAD` move from `5090aee1` to `1195512adc0e`.
     The worktree still has `322` tracked dirty rows, `16049` default status
     rows, and `322 files changed, 202122 insertions(+), 30187 deletions(-)`.
     Current lane statuses still say `pending`, `uncommitted`, or `not
     committed` for the latest lane handoffs.

2. **Critical - there is no acceptable repo-wide PHP result for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`,
     `lanes/*/lane-status.json:12`.
   - Requirement at risk: `goal.md:49` requires repo-wide tests and static
     checks to be run periodically with failures recorded honestly.
   - Evidence: the pre-root process gate initially matched active PHP harness
     PID `3870942`; the owner sample missed it after exit. A later gate cleared,
     but the checkout had moved and remained dirty. The pre-commit gate then
     matched active no-argument root PID `3943109` owned by `claude`, plus
     focused lane shards, so an audit-owned duplicate root run was forbidden.

3. **Critical - `porting.html` and `porting-summary.json` remain stale
   publication artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting.html:77`, `dependency-backlog.json:3`.
   - Requirement at risk: `goal.md:45` requires the dashboard to show current
     per-lane denominator, mapped tests, PHP pass/fail, phase, audit, work,
     blocker, and commit.
   - Evidence: the dashboard still reports average progress `97.7%`, generated
     `2026-05-23 23:43:54 UTC`, snapshot `main 79768df0c427`, and `22`
     dependency items. Current `HEAD` is `1195512adc0e`, and
     `dependency-backlog.json` has `24` rows.

4. **High - dashboard, manifest, and lane-status counts disagree across the
   portfolio.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require comparable
     upstream denominators, mapped-test counts, PHP pass/fail counts, and
     current commit status.
   - Evidence: current manifest/status values versus dashboard include
     Difftastic `917 / 644 / 2970` vs `735 / 374 / 374`; Dolt prose total /
     `613 / 409` vs `inventory / 613 / 356`; esbuild `2567 / 388 / 388` vs
     `2567 / 311 / 311`; Gitoxide `2877 / 2877 / 6615` vs
     `2877 / 2751 / 5634`; libsqlite `1589 / 330 / 330` vs
     `1589 / 286 / 286`; LightningCSS manifest/status `3532 / 2661 / 3836`
     vs dashboard `3532 / 1732 / 2197`; markerPDF `371 / 322 / 459` vs
     `330 / 280 / 416`; Pandoc `2276 / 1714 / 334` vs `2276 / 1061 / 278`;
     Quadrable `55 / 55 / 218` vs `55 / 55 / 190`; rclone
     `1601 / 849 / 849` vs `1601 / 698 / 698`; Readability
     `1984 / 1984 / 238` vs `1984 / 1984 / 204`; Syncthing
     `658 / 658 / 7064` vs `658 / 658 / 4579`.

5. **High - manifest/status schema remains non-normalized, with Dolt still
   internally inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2478`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2484`,
     `lanes/dolt/lane-status.json:5`, `lanes/dolt/lane-status.json:6`,
     `lanes/dolt/lane-status.json:13`.
   - Requirement at risk: `goal.md:25` requires a real upstream benchmark
     denominator; `goal.md:3` requires durable coordination fields.
   - Evidence: Dolt's canonical `benchmarkDenominator.total` is a long
     FIND_IN_SET prose string, while the latest slice is STRCMP. The manifest
     reports `phpBehaviorTests = 398`, while lane status reports `phpPass =
     409`. The latest status also says the lane is not committed.

6. **High - every lane remains a pending dirty handoff, not an accepted small
   slice.**
   - Paths: `lanes/*/lane-status.json:13`, `progress.md:39`.
   - Requirement at risk: `goal.md:29` requires small reviewable commits;
     `goal.md:48` requires verified integration of completed agent output.
   - Evidence: recent history remains dominated by audit/hold commits, while
     every lane status still records `pending`, `uncommitted`, or
     `not committed` latest-commit text. The dirty surface spans all 12 lanes
     plus coordination files.

7. **High - near-complete dashboard percentages overstate accepted parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:967`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:21`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:188`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1587`.
   - Requirement at risk: `goal.md:35` says passing tests are not enough, and
     `goal.md:37` says upstream tests are the source of truth where possible.
   - Evidence: the dashboard still shows `92%` to `99%`, but several current
     manifests explicitly warn that full upstream runner parity is absent:
     Difftastic full Cargo, Gitoxide full Cargo workspace, Pandoc Haskell
     Tasty/Cabal, and Syncthing full `go test ./...` remain unexecuted.

8. **High - essential optional-library coverage is still backlog-only.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:25`,
     `dependency-backlog.json:185`, `dependency-backlog.json:354`,
     `dependency-backlog.json:400`, `dependency-backlog.json:438`,
     `porting.html:75`.
   - Requirement at risk: support libraries must have a bounded native PHP
     component, activation gate, dependency-specific upstream/spec
     denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt
     cases where relevant, and executable upstream/spec evidence where
     possible.
   - Evidence: the backlog has `24` rows and zero active bounded ports, while
     the dashboard still shows `22`. Rich gaps remain for ZIP/package,
     XML/HTML5, DOCX/OpenXML, legacy DOC/CFB, EPUB, doctemplates, citations,
     math/TeX, PDF text/layout/OCR, Unicode/charset, source maps, tree-sitter
     subsets, protobuf, checksums, SQL/storage codecs, archive/compression,
     glob/pathspec, and provider metadata.

9. **High - rclone dependency expansion is broad and lane-local, so it should
   not count as shared optional-library progress.**
   - Paths: `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:8`, `lanes/rclone/lane-status.json:9`,
     `lanes/rclone/lane-status.json:12`, `dependency-backlog.json:25`,
     `dependency-backlog.json:400`, `dependency-backlog.json:438`.
   - Requirement at risk: support-library expansion must be bounded, gated,
     tested, shared where appropriate, and backed by dependency-specific
     denominators.
   - Evidence: rclone now carries WebDAV XML, PROPFIND/PROPPATCH, LOCK/If,
     COPY/MOVE, gzip, serve middleware, auth-proxy, custom directory-template,
     OneDrive/provider metadata, and x/net copyProps behavior in lane-local
     slices. These are useful rclone behaviors, but not shared XML/WebDAV,
     compression, provider, checksum, or pathspec support-library progress.

10. **High - markerPDF still mixes native PDF work with external/runtime
    orchestration plans.**
    - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:489`,
      `lanes/markerpdf/lane-status.json:5`,
      `lanes/markerpdf/lane-status.json:12`.
    - Requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
      wrappers, bridge calls, shell-outs, or external converter/runtime
      execution as native port progress.
    - Evidence: markerPDF has real native PDF text/filter/font work, but its
      manifest/status still carry marker_server, marker_app, convert.py,
      chunk_convert, pdftext, Tesseract, Ghostscript, OCRMyPDF, Pandoc/XeLaTeX,
      Poetry, Streamlit, FastAPI/Uvicorn, Torch, Surya, Texify, Nougat, and
      multiprocessing lifecycle plans. Those must remain preflight/oracle
      metadata unless bounded PHP components own the behavior.

11. **Medium - Gitoxide shell-outs in tests must remain oracle tooling, not
    native progress evidence.**
    - Paths: `lanes/gitoxide/tests/FetchResponseTest.php:16`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:11`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/gitoxide/tests/ReceivePackTransportTest.php:1167`,
      `lanes/gitoxide/tests/ReceivePackTransportTest.php:1366`,
      `lanes/gitoxide/lane-status.json:12`.
    - Requirement at risk: `goal.md:30` says generated fixtures, bridge calls,
      and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide includes some `proc_open`/`git` fixture readers and
      injected exec/shell command planning. That can be valid oracle or
      transport-planning coverage, but it must stay labeled as such and not
      inflate native implementation parity.

## Next Best Intervention

Freeze active writers, dashboard/status publishers, focused lane harnesses, and
root loops; wait for two stable dirty-count and HEAD polls; accept or reject one
lane batch at a time; normalize manifest/status numeric fields and commit fields;
run focused verification plus `git diff --check`; regenerate `progress.md`,
`porting.html`, and `porting-summary.json` from the accepted commit; then run
one serialized no-argument `php tools/run-tests.php` only if the exact process
gate remains empty.
