# Independent Audit - 2026-05-24T07:48Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`, recent Git
history, and the required PHP harness process gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T07:40Z through 2026-05-24T07:48:27Z
HEAD moved during audit: a9da87aa -> 8ae8ed15 -> 2b00e6a2
recent commits: 2b00e6a2 Record integration hold status; 8ae8ed15 Record integration hold status; a9da87aa Refresh independent audit status
branch divergence: main...origin/main [ahead 763, behind 68]
tracked dirty rows: 315 -> 319 -> 321
default status rows including untracked: 15562 -> 15624 -> 15633 -> 15695
git diff --shortstat: 315 files changed, 194342 insertions(+), 27573 deletions(-) -> 315 files changed, 194679 insertions(+), 27595 deletions(-) -> 319 files changed, 195949 insertions(+), 28639 deletions(-) -> 321 files changed, 196176 insertions(+), 28661 deletions(-)
manifest/status JSON validation: jq reads succeeded for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dependency backlog: 23 items; 13 candidate, 10 deferred; no active support-library port
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
root run by this audit: not started
```

Required root-run gate evidence:

```text
Initial pgrep -af '^php tools/run-tests\.php( |$)': no rows.

2026-05-24T07:42:43Z pgrep -af '^php tools/run-tests\.php( |$)':
3362311 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterTest.php ...
3367850 php tools/run-tests.php lanes/readability/tests/ArticleExtractorTest.php

Owner sampling: both 3362311 and 3367850 exited before ps could sample them.

2026-05-24T07:43:17Z pgrep -af '^php tools/run-tests\.php( |$)':
3370923 php tools/run-tests.php lanes/syncthing/tests
3372369 php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php

Owner evidence captured:
3370923 claude 3319722 Sun May 24 07:43:06 2026 00:18 R php tools/run-tests.php lanes/syncthing/tests

2026-05-24T07:43:24Z pgrep -af '^php tools/run-tests\.php( |$)':
3370923 php tools/run-tests.php lanes/syncthing/tests

Exact no-argument root-only check, pgrep -af '^php tools/run-tests\.php$':
no rows

2026-05-24T07:46:35Z pgrep -af '^php tools/run-tests\.php( |$)':
3398767 php tools/run-tests.php
3400474 php tools/run-tests.php lanes/quadrable/tests
3400882 php tools/run-tests.php lanes/readability/tests
3400959 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
3401011 php tools/run-tests.php lanes/syncthing/tests/ConfigDevicesTest.php ...
3401233 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterTest.php ...

Owner evidence captured:
3398767 claude 3398696 Sun May 24 07:46:27 2026 00:13 R php tools/run-tests.php
3400474 claude 3400204 Sun May 24 07:46:32 2026 00:08 R php tools/run-tests.php lanes/quadrable/tests
3400882 claude 3400719 Sun May 24 07:46:32 2026 00:07 R php tools/run-tests.php lanes/readability/tests
3400959 claude 3400812 Sun May 24 07:46:32 2026 00:07 R php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
3401011 claude 3400884 Sun May 24 07:46:32 2026 00:07 R php tools/run-tests.php lanes/syncthing/tests/ConfigDevicesTest.php ...
3401233 claude 3401125 Sun May 24 07:46:33 2026 00:06 R php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterTest.php ...

2026-05-24T07:48:20Z pgrep -af '^php tools/run-tests\.php( |$)':
no rows
```

I did not start `php tools/run-tests.php`. The later required gate matched an
active no-argument root harness owned by `claude` plus focused PHP shards. The
final gate then cleared, but the checkout moved again before the commit attempt.
Starting a no-argument root run from that state would have produced another
moving-snapshot anecdote, not an acceptance result.

Additional checks run by this audit:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json dependency-backlog.json
passed

rg for process shell-outs in PHP source/tests:
tools/generate-dashboard.php: shell_exec() for git metadata
lanes/gitoxide/tests/GitUrlTest.php, FetchV2SessionTest.php, FetchResponseTest.php: proc_open() oracle/test helpers
lanes/markerpdf/src/ChunkConversionPlanner.php: non-executing shell lifecycle metadata
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md`, `audits/integration-status.md`, recent Git history.
   - Requirement at risk: `goal.md` requires small, reviewable committed slices
     with passing tests and honest status before integration.
   - Evidence: `HEAD` moved during this audit from `a9da87aa` through
     `8ae8ed15` to `2b00e6a2`; default status rows moved from `15562` to
     `15695`; tracked dirty rows moved from `315` to `321`; shortstat changed
     from `194342 insertions(+), 27573 deletions(-)` to `196176 insertions(+),
     28661 deletions(-)`. The current history is still alternating
     `Refresh independent audit status` and `Record integration hold status`
     commits rather than accepted lane slices.

2. **Critical - there is no acceptable root-harness result for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `audits/latest.md`, `progress.md`.
   - Requirement at risk: `goal.md` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: no audit-owned root run was started. The required gate was
     initially clear, then found focused PHP harnesses `3362311`, `3367850`,
     `3370923`, and `3372369`; a later gate found active no-argument root PID
     `3398767` owned by `claude` plus focused PHP shards `3400474`, `3400882`,
     `3400959`, `3401011`, and `3401233`. A final gate was clear, but the tree
     changed again between status samples, so any audit-owned root result would
     not correspond to a frozen source snapshot.

3. **Critical - the published dashboard is stale and violates the dashboard
   contract.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:1`.
   - Requirement at risk: `goal.md` requires an at-a-glance dashboard with
     current average progress, denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html` still reports average progress `97.7%`,
     generated `2026-05-23 23:43:54 UTC`, and source snapshot
     `79768df0c427`, while current `HEAD` is `2b00e6a2`. The dashboard shows
     `22` dependency rows, but `dependency-backlog.json` has `23` items,
     including `pandoc-doctemplates-core`.

4. **High - dashboard, manifest, and lane-status counts disagree across active
   lanes.**
   - Paths: `porting.html`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md` requires reliable upstream denominators,
     mapped test counts, PHP pass/fail counts, blockers, and current status.
   - Evidence: current manifest/status values versus dashboard include:
     Difftastic `895 total / 614 mapped / 2931 pass` vs dashboard
     `735 / 374 / 374`; Dolt prose total / `613 mapped / 405 pass` vs
     `inventory / 613 / 356`; esbuild `2567 / 385 / 385` vs
     `2567 / 311 / 311`; Gitoxide `2877 / 2877 / 6510` vs
     `2877 / 2751 / 5634`; libsqlite `1589 / 327 / 327` vs
     `1589 / 286 / 286`; LightningCSS `3532 / 2625 / 3682` vs
     `3532 / 1732 / 2197`; markerPDF `368 / 319 / 456` vs
     `330 / 280 / 416`; Pandoc `2276 / 1676 / 331` vs
     `2276 / 1061 / 278`; Quadrable `55 / 55 / 216` vs `55 / 55 / 190`;
     rclone `1601 / 840 / 840` vs `1601 / 698 / 698`; Readability
     `1984 / 1984 / 236` vs `1984 / 1984 / 204`; Syncthing
     `658 / 658 / 6978` vs `658 / 658 / 4579`.

5. **High - `progress.md` remains stale as a coordination surface.**
   - Paths: `progress.md:97`, `progress.md:103`, `progress.md:114`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md` requires `progress.md` to include current
     active lanes, blockers, owners/sessions, next task, and percentage
     estimates.
   - Evidence: the active-lanes table still names old handoffs such as
     Gitoxide SSH config-options, LightningCSS trig/math, markerPDF benchmark
     file-inventory, Readability negative header cleanup, Pandoc NativeWriter
     figure/citation, Syncthing system log, rclone VFS Statfs/usage, and
     esbuild automatic JSX key/spread fallback. Current lane statuses instead
     report Gitoxide index entry-order verification, LightningCSS XYZ-family
     color-mix, markerPDF indirect PDF font encoding resolution, Readability
     Firefox Nightly parity, Pandoc DOCX table grid-before behavior, Syncthing
     config ignored-device restart-state behavior, rclone WebDAV descendant
     COPY, and esbuild template literal lowering.

6. **High - manifest schema is still not normalized enough for durable
   coordination.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2470`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `tools/generate-dashboard.php`.
   - Requirement at risk: `goal.md` requires real upstream denominators and
     comparable mapped/PHP pass-fail fields.
   - Evidence: Dolt's canonical `benchmarkDenominator.total` is a long
     FIND_IN_SET runner narrative instead of a numeric denominator. Several
     manifests expose native PHP evidence in lane-specific prose rather than a
     stable `nativeImplementation` schema; straightforward jq reads of
     `nativeImplementation.phpPassingTests`, `phpFailingTests`, and
     `mappedUpstreamTests` return `null` across the manifests.

7. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html`, `porting-summary.json`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md` says passing tests are not enough; each
     lane needs meaningful upstream parity, fixture parity, error behavior, and
     honest blockers.
   - Evidence: dashboard rows report `92.0%` to `99.0%`, but current
     lane-status handoffs repeatedly say root aggregate verification remains
     pending, the latest work is uncommitted/pending, and full upstream runners
     remain bounded, unavailable, or unexecuted for major lanes.

8. **High - essential optional-library coverage remains backlog-only, not
   accepted support-library ports.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:111`,
     `dependency-backlog.json:169`, `porting.html:75`.
   - Requirement at risk: this audit requires support libraries to have a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant.
   - Evidence: `dependency-backlog.json` has `23` items, all `candidate` or
     `deferred`, and no active support-library port. The dashboard still shows
     `22` rows and omits `pandoc-doctemplates-core`. Rich-function gaps remain
     for ZIP/package, XML/HTML5, DOCX/OpenXML, legacy DOC/CFB, EPUB, ODT,
     doctemplates, citations/CSL, math/TeX, PDF text, PDF render planning,
     OCR/layout, table geometry, Unicode, charset, source maps, protobuf,
     checksum/hash, SQL/storage, archive streams, glob/pathspec, and provider
     metadata.

9. **High - rclone dependency expansion is too broad and lane-local to count
   as shared optional-library progress.**
   - Paths: `lanes/rclone/lane-status.json:8`,
     `lanes/rclone/lane-status.json:9`, `dependency-backlog.json`.
   - Requirement at risk: dependency expansion must be bounded, gated, tested,
     shared where appropriate, and backed by a dependency-specific denominator.
   - Evidence: rclone status now includes lane-local WebDAV XML/PROPFIND/
     PROPPATCH/LOCK/If handling, gzip, middleware, auth-proxy, directory
     templates, URL decoding, VFS, and provider metadata work. These may be
     valid rclone slices, but they are not accepted shared support-library
     ports without their own denominators, malformed/corrupt cases, activation
     gates, and reusable ownership.

10. **High - markerPDF still mixes native evidence with external/runtime
    orchestration plans.**
    - Paths: `lanes/markerpdf/lane-status.json:5`,
      `lanes/markerpdf/lane-status.json:9`,
      `lanes/markerpdf/lane-status.json:12`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
    - Requirement at risk: `goal.md` forbids counting wrappers, bridge calls,
      shell-outs, or external converter/runtime execution as native port
      progress.
    - Evidence: markerPDF has real native PDF stream/filter/CMap/font-decoding
      work, but its status still carries marker_server, marker_app,
      chunk_convert, convert.py, Tesseract, Ghostscript, Pandoc/XeLaTeX,
      Poetry, Streamlit, FastAPI/Uvicorn, Torch, Surya, Texify, Nougat, and
      multiprocessing lifecycle plans. Those remain preflight/oracle metadata
      unless a bounded native PHP component owns the behavior.

11. **Medium - shell-outs are still present in tooling and tests and must stay
    outside native progress credit.**
    - Paths: `tools/generate-dashboard.php:197`,
      `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/markerpdf/src/ChunkConversionPlanner.php:142`.
    - Requirement at risk: `goal.md` requires native ports and only allows
      bridge code as temporary fixture-generation or oracle tooling.
    - Evidence: targeted PHP search found `shell_exec()` for dashboard git
      metadata, `proc_open()` in Gitoxide tests, and markerPDF shell lifecycle
      metadata. No lane implementation process shell-out was accepted by this
      audit as native progress.

## Next Intervention

Keep the hard writer/runner/status freeze as the next gate. Stop or wait out
active writers/status publishers and PHP shards; take two stable polls of
`HEAD`, tracked/default status rows, shortstat, exact PHP runner state, focused
runner state, Dolt runner state, dashboard state, and relevant log mtimes;
accept or reject one lane-scoped batch; normalize schema and count fields for
that batch; run focused verification plus `git diff --check`; run exactly one
serialized no-argument `php tools/run-tests.php` from that same frozen snapshot
only if the exact process gate is empty; regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from the accepted
commit; then commit or reject.
