# Independent Audit - 2026-05-24T08:30Z

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
UTC samples: 2026-05-24T08:26Z through 2026-05-24T08:30Z
HEAD observed during audit: 3d6cb64885c3 -> e3be5bb8a540
recent history: e3be5bb8 Record integration hold status; 3d6cb648 Refresh independent audit status; 2b331ff3 Record integration hold status
branch divergence: main...origin/main [ahead 778, behind 68]
default status rows: 16121
git diff --shortstat: 322 files changed, 203034 insertions(+), 30289 deletions(-)
manifest/status JSON validation: jq empty passed for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dashboard snapshot: porting.html and porting-summary.json still generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 08:10:35 UTC with 24 rows; dashboard still shows 22
root run by this audit: not started
```

Required pre-root process-gate evidence:

```text
2026-05-24T08:26Z pgrep -af '^php tools/run-tests\.php( |$)':
3974435 php tools/run-tests.php lanes/syncthing/tests

2026-05-24T08:26Z ps -o pid,ppid,user,stat,etime,args -p 3974435:
3974435 3880157 claude Rs 00:56 php tools/run-tests.php lanes/syncthing/tests

2026-05-24T08:28Z later pgrep -af '^php tools/run-tests\.php( |$)':
no rows

2026-05-24T08:29Z pgrep -af '^php tools/run-tests\.php( |$)':
3990919 php tools/run-tests.php lanes/readability/tests

2026-05-24T08:29Z ps -o pid,ppid,user,stat,etime,args -p 3990919:
process had exited before owner sampling; only the ps header returned

2026-05-24T08:30Z final pgrep -af '^php tools/run-tests\.php( |$)':
no rows
```

I did not start `php tools/run-tests.php`. The exact gate matched active PHP
harnesses during the audit, and when it later cleared the checkout had already
moved and remained a broad dirty aggregate. A no-argument root run from that
state would not be a stable acceptance signal.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:37`, `lanes/*/lane-status.json:13`, recent Git
     history.
   - Requirement at risk: `goal.md:29` requires small reviewable slices with
     passing tests; `goal.md:48` requires finished agent output to be verified,
     committed, and integrated cleanly.
   - Evidence: during this audit `HEAD` moved from `3d6cb64885c3` to
     `e3be5bb8a540`. The worktree still reports `16121` status rows and
     `322 files changed, 203034 insertions(+), 30289 deletions(-)`. Every lane
     status still records `pending`, `uncommitted`, or `not committed` latest
     commit text.

2. **Critical - there is no acceptable repo-wide PHP result for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:37`,
     `lanes/*/lane-status.json:12`.
   - Requirement at risk: `goal.md:49` requires repo-wide tests and static
     checks to be run periodically with failures recorded honestly.
   - Evidence: the required pre-root gate matched active PHP harness PID
     `3974435` owned by `claude` and later transient PID `3990919`. The gate
     cleared only after `HEAD` and dirty counts had moved again. No serialized
     no-argument root result exists for `e3be5bb8a540` plus the current dirty
     tree.

3. **Critical - `porting.html` and `porting-summary.json` remain stale
   publication artifacts.**
   - Paths: `porting.html:34`, `porting.html:35`, `porting.html:38`,
     `porting.html:75`, `dependency-backlog.json:3`, `porting-summary.json`.
   - Requirement at risk: `goal.md:45` requires the dashboard to show current
     per-lane denominator, mapped tests, PHP pass/fail, phase, audit, current
     work, blocker, and commit.
   - Evidence: the dashboard still reports generated
     `2026-05-23 23:43:54 UTC`, snapshot `main 79768df0c427`, and `22`
     dependency items. Current `HEAD` is `e3be5bb8a540`, and
     `dependency-backlog.json` has `24` rows.

4. **High - dashboard, manifest, and lane-status counts disagree across all
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require comparable
     upstream denominators, mapped-test counts, PHP pass/fail counts, and
     current commit status.
   - Evidence: current manifest/status values versus dashboard include
     Difftastic `917 / 644 / 2970` vs `735 / 374 / 374`; Dolt prose total /
     `613 / 409` vs `inventory / 613 / 356`; esbuild `2567 / 389 / 389` vs
     `2567 / 311 / 311`; Gitoxide `2877 / 2877 / 6632` vs
     `2877 / 2751 / 5634`; libsqlite `1589 / 331 / 331` vs
     `1589 / 286 / 286`; LightningCSS `3532 / 2661 / 3842` vs
     `3532 / 1732 / 2197`; markerPDF `372 / 323 / 460` vs
     `330 / 280 / 416`; Pandoc `2276 / 1720 / 335` vs
     `2276 / 1061 / 278`; Quadrable `55 / 55 / 219` vs `55 / 55 / 190`;
     rclone `1601 / 849 / 851` vs `1601 / 698 / 698`; Readability
     `1984 / 1984 / 238` vs `1984 / 1984 / 204`; Syncthing
     `658 / 658 / 7064` vs `658 / 658 / 4579`.

5. **High - manifest/status schema remains non-normalized, with Dolt still
   internally inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2481`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2487`,
     `lanes/dolt/lane-status.json:5`, `lanes/dolt/lane-status.json:6`,
     `lanes/dolt/lane-status.json:13`.
   - Requirement at risk: `goal.md:25` requires a real upstream benchmark
     denominator; `goal.md:3` requires durable coordination fields.
   - Evidence: Dolt's canonical `benchmarkDenominator.total` is still a long
     prose string from an older FIND_IN_SET slice, while the latest manifest
     slice is MD5/SHA and lane status reports STRCMP work. The manifest reports
     `nativeImplementation.phpBehaviorTests = 400`, while lane status reports
     `phpPass = 409`.

6. **High - every lane remains a pending dirty handoff, not an accepted small
   slice.**
   - Paths: `lanes/*/lane-status.json:13`, `progress.md:37`.
   - Requirement at risk: `goal.md:29` requires small reviewable commits;
     `goal.md:48` requires verified integration of completed agent output.
   - Evidence: recent history is still dominated by audit and integration-hold
     commits, while all 12 lane status files report pending or uncommitted
     latest work. The dirty surface spans all 12 lanes plus coordination files.

7. **High - near-complete dashboard percentages overstate accepted parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:967`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:191`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:16`.
   - Requirement at risk: `goal.md:35` says passing tests are not enough, and
     `goal.md:37` says upstream tests are the source of truth where possible.
   - Evidence: the dashboard still shows `92%` to `99%`, but Difftastic full
     Cargo, Gitoxide full Cargo workspace, Pandoc Haskell Tasty/Cabal, and
     Syncthing full `go test ./...` parity remain unexecuted or explicitly
     out of scope for the current workers.

8. **High - essential optional-library coverage is still backlog-only.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:25`,
     `dependency-backlog.json:117`, `dependency-backlog.json:187`,
     `dependency-backlog.json:398`, `dependency-backlog.json:438`,
     `porting.html:75`.
   - Requirement at risk: support libraries must have a bounded native PHP
     component, activation gate, dependency-specific upstream/spec denominator,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where
     relevant, and executable upstream/spec evidence where possible.
   - Evidence: the backlog has `24` rows and zero active bounded support-library
     ports, while the dashboard still shows `22`. Rich gaps remain for ZIP,
     XML/HTML5, DOCX/OpenXML, legacy DOC/CFB, EPUB, doctemplates, citations,
     math/TeX, PDF engine handoff, PDF text/layout/OCR, Unicode/charset, source
     maps, tree-sitter subsets, protobuf, checksums, SQL/storage codecs,
     archive/compression, glob/pathspec, and provider metadata.

9. **High - rclone dependency expansion is broad and lane-local, so it should
   not count as shared optional-library progress.**
   - Paths: `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:8`, `lanes/rclone/lane-status.json:9`,
     `lanes/rclone/lane-status.json:12`, `dependency-backlog.json:25`,
     `dependency-backlog.json:398`, `dependency-backlog.json:438`.
   - Requirement at risk: support-library expansion must be bounded, gated,
     tested, shared where appropriate, and backed by dependency-specific
     denominators.
   - Evidence: rclone now carries WebDAV XML, PROPFIND/PROPPATCH, LOCK/If,
     COPY/MOVE, gzip, serve middleware, auth-proxy, custom directory-template,
     OneDrive/provider metadata, and x/net WebDAV behavior in lane-local slices.
     These are useful rclone behaviors, but not shared XML/WebDAV, compression,
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
    - Paths: `lanes/gitoxide/tests/FetchResponseTest.php:16`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:11`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/gitoxide/tests/ReceivePackTransportTest.php:1366`,
      `lanes/gitoxide/lane-status.json:12`.
    - Requirement at risk: `goal.md:30` says generated fixtures, bridge calls,
      and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide includes `proc_open`/`git` fixture readers and
      shell-command planning coverage. That can be valid oracle or transport
      planning evidence, but it must stay explicitly labeled and must not
      inflate native implementation parity.

## Next Best Intervention

Freeze active writers, dashboard/status publishers, focused lane harnesses, and
root loops; wait for two stable dirty-count and HEAD polls; accept or reject one
lane batch at a time; normalize manifest/status numeric fields and commit
fields; run focused verification plus `git diff --check`; regenerate
`progress.md`, `porting.html`, and `porting-summary.json` from the accepted
commit; then run one serialized no-argument `php tools/run-tests.php` only if
the exact process gate remains empty.
