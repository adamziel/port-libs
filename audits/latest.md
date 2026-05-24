# Independent Audit - 2026-05-24T08:38Z

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
UTC samples: 2026-05-24T08:33Z through 2026-05-24T08:37Z
HEAD observed during audit: bbfa5ba9b7a0 -> c7efb7dbc80c
recent history: c7efb7db Record integration hold status; a66862f5 Record integration hold status; bbfa5ba9 Refresh independent audit status
branch divergence: main...origin/main [ahead 781, behind 68]
tracked dirty rows: 322 -> 324
default status rows: 16188 -> 16194
git diff --shortstat: 322 files changed, 203374 insertions(+), 30297 deletions(-) -> 324 files changed, 204023 insertions(+), 30495 deletions(-)
manifest/status JSON validation: jq empty passed for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dashboard snapshot: porting.html and porting-summary.json still generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 08:10:35 UTC with 24 rows; dashboard still shows 22
root run by this audit: not started
```

Required pre-root process-gate evidence:

```text
2026-05-24T08:33Z pgrep -af '^php tools/run-tests\.php( |$)':
no rows

2026-05-24T08:34Z pgrep -af '^php tools/run-tests\.php( |$)':
no rows

2026-05-24T08:35Z pgrep -af '^php tools/run-tests\.php( |$)':
no rows

2026-05-24T08:37Z pgrep -af '^php tools/run-tests\.php( |$)':
4048987 php tools/run-tests.php lanes/syncthing/tests

2026-05-24T08:37Z ps -o pid,ppid,user,stat,etime,args -p 4048987:
4048987 4009864 claude Rs 00:37 php tools/run-tests.php lanes/syncthing/tests

2026-05-24T08:38Z final pgrep -af '^php tools/run-tests\.php( |$)':
no rows
```

I did not start `php tools/run-tests.php`. The exact process gate was initially
clear, but the checkout was not stable enough for an acceptance root run:
`HEAD` moved during the audit, the dirty shortstat kept changing, and by the
later gate a focused Syncthing PHP harness was active.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:37`, `lanes/*/lane-status.json:13`, recent Git
     history.
   - Requirement at risk: `goal.md:29` requires small reviewable slices with
     passing tests; `goal.md:48` requires finished agent output to be verified,
     committed, and integrated cleanly.
   - Evidence: `HEAD` moved from `bbfa5ba9b7a0` to `c7efb7dbc80c` during this
     audit. The tree has `324` tracked dirty paths, including audit/progress
     edits, across all 12 lanes, `16194` default status rows, and every lane status still reports
     `pending`, `uncommitted`, `not committed`, or a stale `HEAD` text in
     `latestCommit`.

2. **Critical - there is no acceptable repo-wide PHP result for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:37`,
     `lanes/*/lane-status.json:12`.
   - Requirement at risk: `goal.md:49` requires repo-wide tests and static
     checks to be run periodically with failures recorded honestly.
   - Evidence: the required `pgrep -af '^php tools/run-tests\.php( |$)'` gate
     returned no rows at first, but the tree changed during the stability
     window and then matched active focused Syncthing harness PID `4048987`
     owned by `claude`. No serialized no-argument root result exists for
     `c7efb7dbc80c` plus the current dirty tree.

3. **Critical - `porting.html` and `porting-summary.json` remain stale
   publication artifacts.**
   - Paths: `porting.html:34`, `porting.html:35`, `porting.html:38`,
     `porting.html:75`, `porting-summary.json`, `dependency-backlog.json:3`.
   - Requirement at risk: `goal.md:45` requires the dashboard to show current
     per-lane denominator, mapped tests, PHP pass/fail, phase, audit, current
     work, blocker, and commit.
   - Evidence: the dashboard still reports generated
     `2026-05-23 23:43:54 UTC`, source snapshot `main 79768df0c427`, and `22`
     dependency rows. Current observed `HEAD` is `c7efb7dbc80c`, and
     `dependency-backlog.json` has `24` rows.

4. **High - dashboard, manifest, and lane-status counts disagree across all
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require comparable
     upstream denominators, mapped-test counts, PHP pass/fail counts, and
     current commit status.
   - Evidence: current manifest/status values versus dashboard include
     Difftastic `925 / 656 / 2970` vs `735 / 374 / 374`; Dolt prose total /
     `613 / 411` vs `inventory / 613 / 356`; esbuild `2567 / 390 / 389` vs
     `2567 / 311 / 311`; Gitoxide `2877 / 2877 / 6632` vs
     `2877 / 2751 / 5634`; libsqlite `1589 / 332 / 332` vs
     `1589 / 286 / 286`; LightningCSS `3535 / 2664 / 3842` vs
     `3532 / 1732 / 2197`; markerPDF `372 / 323 / 460` vs
     `330 / 280 / 416`; Pandoc `2276 / 1720 / 335` vs `2276 / 1061 / 278`;
     Quadrable `55 / 55 / 219` vs `55 / 55 / 190`; rclone
     `1601 / 851 / 851` vs `1601 / 698 / 698`; Readability
     `1984 / 1984 / 239` vs `1984 / 1984 / 204`; Syncthing
     `658 / 658 / 7106` vs `658 / 658 / 4579`.

5. **High - manifest/status schema remains non-normalized, with Dolt still
   internally inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2483`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2489`,
     `lanes/dolt/lane-status.json:5`, `lanes/dolt/lane-status.json:6`,
     `lanes/dolt/lane-status.json:13`.
   - Requirement at risk: `goal.md:25` requires a real upstream benchmark
     denominator; `goal.md:3` requires durable coordination fields.
   - Evidence: Dolt's canonical `benchmarkDenominator.total` is still a long
     prose string. The manifest records `nativeImplementation.phpBehaviorTests
     = 400`, while lane status reports `phpPass = 411`, and the dashboard still
     displays `356 pass`.

6. **High - every lane remains a pending dirty handoff, not an accepted small
   slice.**
   - Paths: `lanes/*/lane-status.json:13`, `progress.md:37`.
   - Requirement at risk: `goal.md:29` requires small reviewable commits;
     `goal.md:48` requires verified integration of completed agent output.
   - Evidence: recent history is dominated by audit and integration-hold
     commits, while all 12 lane status files report pending or uncommitted
     latest work. The dirty surface spans all 12 lanes plus coordination and
     support-library artifacts.

7. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:629`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:973`.
   - Requirement at risk: `goal.md:35` says passing tests are not enough, and
     `goal.md:37` says upstream tests are the source of truth where possible.
   - Evidence: the dashboard shows `94%` to `99%`, but Difftastic full Cargo,
     Gitoxide full Cargo workspace, Pandoc Haskell Tasty/Cabal, and Syncthing
     full `go test ./...` parity remain unexecuted or explicitly outside the
     current worker evidence.

8. **High - essential optional-library coverage is still backlog-only.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:25`,
     `dependency-backlog.json:111`, `dependency-backlog.json:185`,
     `dependency-backlog.json:398`, `dependency-backlog.json:438`,
     `porting.html:75`.
   - Requirement at risk: support libraries must have a bounded native PHP
     component, activation gate, dependency-specific upstream/spec denominator,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where
     relevant, and executable upstream/spec evidence where possible.
   - Evidence: the backlog now has `24` rows, grouped as `13 candidate` and `11
     deferred`, with zero active bounded support-library ports. The dashboard
     still shows `22` rows. Rich gaps remain for ZIP, XML/HTML5, DOCX/OpenXML,
     legacy DOC/CFB, EPUB, doctemplates, citations, math/TeX, PDF engine
     handoff, PDF text/layout/OCR, Unicode/charset, source maps, tree-sitter
     subsets, protobuf, checksums, SQL/storage codecs, archive/compression,
     glob/pathspec, and provider metadata.

9. **High - rclone dependency expansion is broad and lane-local, so it should
   not count as shared optional-library progress.**
   - Paths: `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:8`, `lanes/rclone/lane-status.json:9`,
     `lanes/rclone/lane-status.json:11`, `dependency-backlog.json:25`,
     `dependency-backlog.json:398`, `dependency-backlog.json:438`.
   - Requirement at risk: support-library expansion must be bounded, gated,
     tested, shared where appropriate, and backed by dependency-specific
     denominators.
   - Evidence: rclone now carries WebDAV XML, PROPFIND/PROPPATCH, LOCK/If,
     COPY/MOVE, gzip, serve middleware, auth-proxy, custom directory-template,
     OneDrive/provider metadata, and x/net WebDAV behavior in lane-local slices.
     Those are useful rclone behaviors, but not shared XML/WebDAV, compression,
     provider, checksum, or pathspec support-library progress.

10. **High - markerPDF still mixes native PDF work with external/runtime
    orchestration plans.**
    - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:491`,
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
    - Paths: `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `lanes/gitoxide/tests/GitUrlTest.php:104`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:1509`,
      `lanes/gitoxide/lane-status.json:12`.
    - Requirement at risk: `goal.md:30` says generated fixtures, bridge calls,
      and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide includes `proc_open`/`git` fixture readers and local
      diagnostic helpers. That can be valid oracle evidence, but it must stay
      explicitly labeled and must not inflate native implementation parity.

## Next Best Intervention

Freeze active writers, dashboard/status publishers, focused lane harnesses, and
root loops; wait for two stable dirty-count and HEAD polls; accept or reject one
lane batch at a time; normalize manifest/status numeric fields and commit
fields; run focused verification plus `git diff --check`; regenerate
`progress.md`, `porting.html`, and `porting-summary.json` from the accepted
commit; then run one serialized no-argument `php tools/run-tests.php` only if
the exact process gate remains empty and the tree stays stable.
