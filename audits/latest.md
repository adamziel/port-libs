# Independent Audit - 2026-05-24T08:42Z

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
UTC samples: 2026-05-24T08:39Z through 2026-05-24T08:42Z
HEAD observed: 9d50ecea584f
recent history: 9d50ecea Refresh independent audit status; c7efb7db Record integration hold status; a66862f5 Record integration hold status
branch divergence: main...origin/main [ahead 782, behind 68]
tracked dirty rows: 322 -> 324
default status rows including untracked: 16286
git diff --shortstat: 322 files changed, 204466 insertions(+), 30382 deletions(-) -> 324 files changed, 204620 insertions(+), 30408 deletions(-)
manifest/status JSON validation: jq empty passed for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 08:10:35 UTC with 24 rows; dashboard/summary still show 22
root run by this audit: not started
```

Required pre-root process-gate evidence:

```text
2026-05-24T08:41Z pgrep -af '^php tools/run-tests\.php( |$)':
no rows

2026-05-24T08:42Z pgrep -af '^php tools/run-tests\.php( |$)':
no rows

2026-05-24T08:45Z post-commit handoff pgrep -af '^php tools/run-tests\.php( |$)':
4176386 php tools/run-tests.php lanes/readability/tests

2026-05-24T08:45Z ps -o pid,ppid,user,stat,etime,args -p 4176386:
no process row; the focused Readability harness exited before owner sampling

2026-05-24T08:45Z final pgrep -af '^php tools/run-tests\.php( |$)':
no rows
```

I did not start `php tools/run-tests.php`. The exact process gate was clear,
but the checkout failed the stability gate: tracked dirty rows and shortstat
changed during the sampling window, and every lane still reports pending or
uncommitted handoff work. A later handoff check briefly saw focused
Readability PID `4176386`, which exited before owner sampling; no duplicate
root run was started.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:39`, `lanes/*/lane-status.json:13`, recent Git
     history.
   - Goal requirement at risk: `goal.md` requires small reviewable slices with
     passing tests and finished agent output to be verified, committed, and
     integrated cleanly.
   - Evidence: the audit observed `HEAD` at `9d50ecea584f`, but the tracked
     dirty surface moved from `322` to `324` paths and the shortstat moved from
     `322 files changed, 204466 insertions(+), 30382 deletions(-)` to `324
     files changed, 204620 insertions(+), 30408 deletions(-)`. All 12
     `lane-status.json` files still report `pending`, `uncommitted`, `not
     committed`, or stale `HEAD` commit text in `latestCommit`.

2. **Critical - there is no acceptable repo-wide PHP result for this snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`,
     `lanes/*/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: `pgrep -af '^php tools/run-tests\.php( |$)'` returned no rows
     at the pre-root samples, but the dirty tree changed during the sampling
     window. A no-argument root run from a moving 324-file dirty aggregate would
     not be acceptance evidence for one frozen snapshot, so this audit did not
     start it.

3. **Critical - `porting.html` and `porting-summary.json` remain stale
   publication artifacts.**
   - Paths: `porting.html:34`, `porting.html:35`, `porting.html:38`,
     `porting.html:75`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:216`,
     `porting-summary.json:218`, `dependency-backlog.json:3`.
   - Goal requirement at risk: `goal.md` requires the dashboard to show current
     per-lane denominator, mapped tests, PHP pass/fail, phase, audit, current
     work, blocker, and commit.
   - Evidence: the dashboard still claims generated
     `2026-05-23 23:43:54 UTC`, snapshot `main 79768df0c427`, and `22`
     dependency rows. Current `HEAD` is `9d50ecea584f`, and
     `dependency-backlog.json` has `24` rows (`13 candidate`, `11 deferred`).

4. **High - dashboard, manifest, and lane-status counts still disagree across
   every active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:9`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires comparable upstream
     denominators, mapped upstream tests, PHP pass/fail counts, audit status,
     and latest commit per lane.
   - Evidence: current manifest/status/dashboard examples include Difftastic
     `925 / 656 / 2980` vs dashboard `735 / 374 / 374`; Dolt prose total /
     `613 / 400` vs status `411` and dashboard `inventory / 613 / 356`;
     esbuild `2567 / 391 / 389` vs status `390` and dashboard `311`;
     Gitoxide `2877 / 2877 / 6833` vs dashboard `2751 / 5634`; libsqlite
     `1589 / 332 / 332` vs dashboard `286`; LightningCSS `3535 / 2664 /
     3847` vs dashboard `3532 / 1732 / 2197`; markerPDF `373 / 324 / 461` vs
     status `460` and dashboard `330 / 280 / 416`; Pandoc `2276 / 1729 / 336`
     vs dashboard `1061 / 278`; Quadrable `55 / 55 / 220` vs dashboard `190`;
     rclone `1601 / 853 / 853` vs dashboard `698`; Readability `1984 / 1984 /
     240` vs dashboard `204`; Syncthing `658 / 658 / 7144` vs dashboard
     `4579`.

5. **High - manifest/status schemas remain non-normalized, with Dolt still
   internally inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2483`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2489`,
     `lanes/dolt/lane-status.json:5`, `lanes/dolt/lane-status.json:6`,
     `lanes/dolt/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator and durable coordination fields.
   - Evidence: Dolt's canonical `benchmarkDenominator.total` is still a long
     prose string. The manifest records `nativeImplementation.phpBehaviorTests
     = 400`, lane status reports `phpPass = 411`, and the stale dashboard
     displays `356 pass`.

6. **High - near-complete percentages overstate accepted upstream/root parity.**
   - Paths: `porting.html:32`, `lanes/difftastic/lane-status.json:5`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:631`,
     `lanes/gitoxide/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:5`,
     `lanes/syncthing/lane-status.json:5`.
   - Goal requirement at risk: `goal.md` says passing PHP tests are not enough,
     upstream tests are the source of truth where possible, and hard features
     must be marked as blockers or future slices.
   - Evidence: the dashboard reports `97.7%` average progress and per-lane
     `94%` to `99%`, while Difftastic full Cargo parity, Gitoxide full Cargo
     workspace parity, Pandoc full Haskell runner parity, and Syncthing full
     `go test ./...` remain unexecuted or outside current evidence.

7. **High - essential optional-library coverage is still backlog-only.**
   - Paths: `dependency-backlog.json:5`, `dependency-backlog.json:7`,
     `dependency-backlog.json:111`, `dependency-backlog.json:169`,
     `dependency-backlog.json:338`, `dependency-backlog.json:354`,
     `dependency-backlog.json:400`, `dependency-backlog.json:438`,
     `porting.html:75`.
   - Goal requirement at risk: support libraries must have the same granularity
     as lanes: bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much of
     the upstream/spec suite as can actually run.
   - Evidence: the backlog has `24` rows, all `candidate` or `deferred`, with
     zero active bounded support-library ports. Rich-function gaps remain for
     ZIP/package, XML/HTML5, DOCX/OpenXML, legacy DOC/CFB, EPUB, doctemplates,
     PDF-engine handoff, PDF text/layout/OCR, Unicode/charset, source maps,
     tree-sitter subsets, protobuf/BEP wire format, checksums, SQL/storage
     codecs, archive/compression, glob/pathspec, and provider metadata.

8. **High - rclone's broad WebDAV/provider/compression expansion is lane-local,
   not shared optional-library progress.**
   - Paths: `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:8`, `lanes/rclone/lane-status.json:9`,
     `lanes/rclone/lane-status.json:12`, `dependency-backlog.json:400`,
     `dependency-backlog.json:438`.
   - Goal requirement at risk: optional dependency expansion must be bounded,
     gated, tested, shared where appropriate, and backed by
     dependency-specific denominators.
   - Evidence: rclone now carries WebDAV XML, PROPFIND/PROPPATCH, LOCK/If,
     COPY/MOVE, gzip, serve middleware, auth-proxy, custom directory-template,
     OneDrive/provider metadata, and x/net WebDAV behavior inside the lane.
     Those can be useful rclone slices, but they are not accepted shared
     XML/WebDAV, archive/compression, provider, checksum, or pathspec support
     library progress.

9. **High - markerPDF still mixes native PDF evidence with external/runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:8`,
     `lanes/markerpdf/lane-status.json:9`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`.
   - Goal requirement at risk: `goal.md` forbids counting wrappers, bridge
     calls, shell-outs, external converter/runtime execution, and whole
     applications as native port progress.
   - Evidence: markerPDF has real native PDF text/filter/font work, but status
     still carries marker_server, marker_app, convert.py, chunk_convert,
     pdftext, Tesseract, Ghostscript, OCRMyPDF, Pandoc/XeLaTeX, Poetry,
     Streamlit, FastAPI/Uvicorn, Torch, Surya, Texify, Nougat, model downloads,
     multiprocessing, and GitHub Actions workflow boundaries. These must remain
     preflight/oracle metadata unless bounded PHP components own the behavior.

10. **Medium - Gitoxide shell-outs in tests must remain oracle tooling, not
    native progress evidence.**
    - Paths: `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `lanes/gitoxide/tests/GitUrlTest.php:104`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:1529`.
    - Goal requirement at risk: `goal.md` says generated fixtures, bridge
      calls, and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide uses `proc_open`/`git` diagnostic helpers and fixture
      readers. That can be valid oracle evidence, but it must stay explicitly
      labeled and must not inflate native implementation parity.

## Next Best Intervention

Freeze active writers, dashboard/status publishers, focused lane harnesses, and
root loops; wait for two stable dirty-count and HEAD polls; accept or reject one
lane batch at a time; normalize manifest/status numeric fields and commit
fields; run focused verification plus `git diff --check`; regenerate
`progress.md`, `porting.html`, and `porting-summary.json` from the accepted
commit; then run one serialized no-argument `php tools/run-tests.php` only if
the exact process gate remains empty and the tree stays stable.
