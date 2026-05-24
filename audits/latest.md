# Independent Audit - 2026-05-24T18:54Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and
recent Git history through `22254a69 Record LightningCSS handoff rejection`.
I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 18:52-18:57
current HEAD: 22254a69af79
recent history: 22254a69 Record LightningCSS handoff rejection; fb8b9a99 Refresh independent audit status; 08647706 Record repeated Readability handoff rejection; 4d13f1d7 Record Readability handoff rejection
branch sample: main...origin/main [ahead 985, behind 68]
tracked dirty rows moved: 330 -> 332
default status rows including untracked moved: 21514 -> 21634
dirty shortstat moved: 330 files changed, 273975 insertions(+), 33464 deletions(-) -> 332 files changed, 274713 insertions(+), 33682 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started; exact no-argument root gate was occupied during audit, then later clear, but the stability gate failed
```

Required exact pre-root process gate:

```text
2026-05-24T18:54:05Z pgrep -af '^php tools/run-tests\.php$': 950914 php tools/run-tests.php
owner evidence: 950914 claude 950861 R+ 01:22 php tools/run-tests.php
2026-05-24T18:56:57Z pgrep -af '^php tools/run-tests\.php$': no rows
```

I did not start `php tools/run-tests.php`. The exact no-argument root harness
gate was occupied in the required audit sample. It later cleared, but the tree
was not a frozen accepted snapshot: default status rows, tracked rows, and
shortstat moved during sampling, lane batches remain uncommitted or pending,
and recent integration commits are rejections rather than accepted
implementation slices.

Latest sampled manifest/status counts. These are samples from a moving
worktree, not an acceptance ledger:

```text
lane          manifest mapped/total     status phpPass field
difftastic    1067/1217                 3658
dolt          613/613                   450 (manifest native PHP ledger still says 442)
esbuild       470/2567                  470
gitoxide      2877/2877                 7594
libsqlite     376/1589                  376
LightningCSS  2980/3548                 4305
markerPDF     378/427                   516
pandoc        2276/2276                 393
quadrable     55/55                     254
rclone        974/1601                  974
readability   1984/1984                 3844
syncthing     658/658                   8941
```

## Findings

1. **Critical - the repository is still a live dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:15`, `progress.md:49-51`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:52` require small reviewable committed slices, verified
     handoffs, and visible stable progress for every lane.
   - Evidence: default status rows including untracked files moved
     `21514 -> 21634` during this audit; tracked dirty rows moved
     `330 -> 332`; shortstat moved from
     `330 files changed, 273975 insertions(+), 33464 deletions(-)` to
     `332 files changed, 274713 insertions(+), 33682 deletions(-)`.
     Every sampled lane status still reports the current work as `pending`,
     `uncommitted`, or supervisor/integrator-owned rather than accepted lane
     commits.

2. **Critical - no trustworthy no-argument root acceptance result exists for the current tree.**
   - Paths: `tools/run-tests.php`, `progress.md:51`,
     `audits/integration-status.md:16-21`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; this audit run also required the exact
     duplicate-root gate before any root run.
   - Evidence: the exact gate matched active no-argument root PID
     `950914 php tools/run-tests.php`, owned by `claude`
     (`950914 claude 950861 R+ 01:22 php tools/run-tests.php`). I did not start
     a duplicate. A later gate sample returned no rows, but the checkout still
     failed the frozen-snapshot gate because dirty counts moved and no
     owner-free reduced lane batch had been accepted.

3. **Critical - `porting.html` and `porting-summary.json` are stale against current lane metadata.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:1-8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, phase,
     audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes source `89260857cc71`, generated
     `2026-05-24 12:29:46 UTC`, while current sampled `HEAD` is
     `22254a69af79`. Current lane files report Difftastic `1067/1217` and
     `3658` pass-field units while the dashboard says `851/1077` and `3245`;
     Dolt `450` pass-field units while the dashboard says `425`; esbuild
     `470` while the dashboard says `429`; libsqlite `376/1589` while the
     dashboard says `349/1589`; LightningCSS `2980/3548` and `4305` while the
     dashboard says `2765/3548` and `4065`; markerPDF `378/427` and `516`
     while the dashboard says `347/396` and `484`; Pandoc `2276/2276` and
     `393` while the dashboard says `1891/2276` and `362`; rclone `974` while
     the dashboard says `906`; Readability `3844` while the dashboard says
     `3545`; and Syncthing `8941` while the dashboard says `7902`.

4. **High - manifest/status ledgers remain non-atomic and internally inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2589-2593`,
     `lanes/dolt/lane-status.json:5-14`,
     `lanes/lightningcss/lane-status.json:10-14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12-16`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:391-395`,
     `porting-summary.json:1-8`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by denominator, mapped
     tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: Pandoc's prior mapped-count overflow is now normalized at
     `2276/2276`, but Dolt still reports `450` PASS cases in lane status while
     the manifest native PHP ledger says `phpBehaviorTests: 442`. LightningCSS
     status still says `HEAD 314f357474f7 at status update` while current
     sampled `HEAD` is `22254a69af79`. Dolt's blocker text names an earlier
     active root PID, while this audit observed PID `950914`. The dashboard
     summary is older still. These are coordination-write and unit-normalization
     defects, not implementation progress.

5. **High - recent integration history confirms the lane batches are too broad for reviewable acceptance.**
   - Paths: `audits/integration-status.md:3-69`,
     `audits/integration-status.md:79-127`,
     `audits/integration-status.md:337-358`,
     `audits/integration-status.md:418-430`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`, and
     `goal.md:48` require small correct slices, passing tests, and supervisor
     verification before committing/integrating output.
   - Evidence: recent history is dominated by rejection commits, including
     LightningCSS, repeated Readability, Quadrable, Pandoc, and Difftastic
     handoff rejections. The integration records say the focused slices may be
     evidenced, but each dirty lane contains broad older modified/untracked
     work. Accepting those batches would conflate unrelated changes under a
     narrow focused test result.

6. **High - support-library coverage is visible but still backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:7-22`,
     `dependency-backlog.json:25-42`,
     `dependency-backlog.json:81-95`,
     `dependency-backlog.json:129-175`,
     `dependency-backlog.json:323-337`,
     `dependency-backlog.json:340-360`,
     `dependency-backlog.json:400-426`,
     `dependency-backlog.json:629-646`,
     `porting.html:71-78`, `progress.md:17-35`.
   - Goal requirement at risk: `goal.md:35-40` require meaningful fixture
     parity, edge-case coverage, upstream tests as source of truth, and
     explicit blockers. The latest support-library directive requires bounded
     native support components with activation gates, dependency-specific
     upstream/spec denominators, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, and bounded `sudo -n`
     install-attempt or ruled-out notes when packages are missing.
   - Evidence: the backlog does route all Pandoc rich-function needs named in
     the latest directive: DOC, DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression. It also routes the other base tools through shared
     WebDAV, URL, source-map, browser-target, package-resolution, tree-sitter,
     sequence-diff, protobuf, checksum, SQL, archive, pathspec, and metadata
     rows. But there are still `0` active support rows, no accepted
     dependency-specific support manifests, no support PHP pass/fail ledgers,
     no malformed/corrupt evidence records, no accepted activation records, and
     no bounded install-attempt notes. None of the current lane-local rich
     slices should receive support-library progress credit.

7. **High - Pandoc remains far short of the original rich conversion-kernel goal despite `99%` status language.**
   - Paths: `goal.md:12`, `lanes/pandoc/lane-status.json:5-14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12-16`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:391-395`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1400-1406`,
     `dependency-backlog.json:81-95`,
     `dependency-backlog.json:129-175`,
     `dependency-backlog.json:413-426`.
   - Goal requirement at risk: `goal.md:12` requires a document conversion
     kernel with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: Pandoc now records `2276/2276` mapped focused checks, but the
     denominator is a cloned static inventory, not full Haskell runner parity.
     The manifest explicitly says it does not invoke upstream Pandoc, live URL
     fetching, browser tooling, converter shell-outs, PDF processing,
     ZIP/package parsing, citation/CSL engines, PlainMath/MathML conversion,
     TeX math/ref conversion, or broader XML/HTML/syntax-highlighting support.
     DOC/DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument,
     citations, math, templates, tables, JSON/YAML metadata, package containers,
     XML/HTML, Unicode, charset, syntax highlighting, and archive/compression
     remain inactive support rows rather than accepted conversion-kernel
     coverage.

8. **High - markerPDF still mixes useful native PDF extraction with plan-only runtime/application evidence.**
   - Paths: `goal.md:9`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12-19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:843-858`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:861-868`,
     `lanes/markerpdf/lane-status.json:5-14`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and
     `goal.md:35-40` say wrappers, shell-outs, and plan-only application
     behavior must not count as native implementation progress.
   - Evidence: the native PDF text extraction slices are useful and explicitly
     avoid Python/pdftext/pypdfium/Poppler/Ghostscript, but the denominator
     still carries Streamlit app plans, FastAPI/Uvicorn server shape, OCR
     install plans, Ghostscript/Tesseract build plans, Poetry/package metadata,
     lockfile/package artifact inventory, Nougat subprocess planning, shell
     lifecycle planning, benchmark archive planning, and model-runtime
     dependency graphs. Those can be coordination/preflight evidence, but not
     native port progress for the PDF extraction pipeline.

9. **Medium - the 98-99 percent progress claims remain misleading.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `porting-summary.json:7-8`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:35-40` require meaningful fixture
     parity, edge-case coverage, upstream tests as source of truth, and
     explicit blockers for hard features.
   - Evidence: the published average remains `98.3%` and most lane statuses say
     `98-99%`, but the dashboard is stale, root acceptance is absent for the
     current tree, all lane work is pending/uncommitted or rejected/deferred,
     the manifest/status units still disagree, full upstream runners remain
     static-only or bounded in several lanes, and no support-library row is
     active.

## Next Intervention

Freeze or wait out active lane workers, root/focused runners, status
publishers, dashboard publishers, evaluator/auditor loops, capacity jobs, and
integrator loops. Require two stable dirty-count/HEAD polls with
`pgrep -af '^php tools/run-tests\.php$'` clear before any audit-owned root run.
The best next intake is a single owner-free reduced lane batch from a frozen
snapshot: normalize Dolt's manifest/status PHP-count drift and all status
commit/PID fields, enforce atomic manifest/status writes, run focused
verification plus `git diff --check`, run exactly one serialized no-argument
`php tools/run-tests.php` only from that frozen snapshot, regenerate
`porting.html` and `porting-summary.json` from the accepted commit, and keep
support-library rows inactive until a base-lane rich slice is accepted or
accepted-blocked on one bounded component.
