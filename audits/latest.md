# Independent Audit - 2026-05-24T18:38Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and
recent Git history through `e3de8087 Record Quadrable handoff rejection`. I
did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 18:24-18:38
observed HEAD movement during audit: fad2bb259fa7 -> 092c10b42df2 -> e3de8087
current HEAD: e3de8087
recent history: e3de8087 Record Quadrable handoff rejection; 092c10b4 Clarify Pandoc rejection next target; 0c98f59e Record Pandoc handoff rejection; fad2bb25 Record Difftastic handoff rejection
branch sample: main...origin/main [ahead 980, behind 68]
tracked dirty rows: 332
default status rows including untracked: 21111
dirty shortstat moved during this audit: 330 files changed, 270545 insertions(+), 32130 deletions(-) -> 330 files changed, 270567 insertions(+), 32134 deletions(-) -> 333 files changed, 271120 insertions(+), 32240 deletions(-) -> 332 files changed, 271047 insertions(+), 32240 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required exact pre-root process gate:

```text
2026-05-24T18:33:47Z pgrep -af '^php tools/run-tests\.php$': 847232 php tools/run-tests.php
2026-05-24T18:34:08Z pgrep -af '^php tools/run-tests\.php$': 847232 php tools/run-tests.php
owner evidence: 847232 claude 838766 Rs 00:28 php tools/run-tests.php
2026-05-24T18:38:22Z final gate sample: 870665 php tools/run-tests.php
final owner evidence: 870665 claude 870558 R+ 00:25 php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. The exact no-argument root harness
gate was occupied, and the checkout was still a moving broad dirty aggregate.

Latest sampled manifest/status counts. These are samples from a moving
worktree, not an acceptance ledger:

```text
lane          latest sampled manifest/status
difftastic    manifest 1058/1213; status 3624 assertions
dolt          manifest 613/613; status 448 PASS cases; manifest native PHP ledger still says 442
esbuild       manifest 469/2567; status 468 tests
gitoxide      manifest 2877/2877; status 7587 assertions
libsqlite     manifest/status 375/1589; status 6014 assertions
LightningCSS  manifest 2967/3548; status 4291 assertions
markerPDF     manifest 377/426; status 515 behavior tests
pandoc        manifest 2268/2276; status 392 behavior tests
quadrable     manifest 55/55; status 253 behavior tests
rclone        manifest/status 972/1601
readability   manifest 1984/1984; status 3839 assertions / 287 behavior tests
syncthing     manifest 658/658; status 8911 assertions
```

## Findings

1. **Critical - the repository is still a live dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:15`, `progress.md:49-51`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:52` require small reviewable committed slices, verified
     handoffs, and visible stable progress for every lane.
   - Evidence: `HEAD` moved during this audit from `fad2bb259fa7` through
     `092c10b42df2` to `e3de8087`. The worktree has `332` tracked dirty rows
     and `21111`
     default status rows including untracked files. The shortstat changed
     during the audit from `270545 insertions(+), 32130 deletions(-)` to
     `271047 insertions(+), 32240 deletions(-)`. Sampled lane statuses still
     describe the latest work as `pending`, `uncommitted`, or root-owned by
     the supervisor/integrator rather than accepted lane commits.

2. **Critical - no trustworthy no-argument root acceptance result exists for the current tree.**
   - Paths: `tools/run-tests.php`, `progress.md:51`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; this audit run also required the exact
     duplicate-root gate before any root run.
   - Evidence: the required `pgrep -af '^php tools/run-tests\.php$'` gate
     matched active root PID `847232 php tools/run-tests.php` at both audit
     stability samples, then final gate sampling matched active root PID
     `870665 php tools/run-tests.php`. Owner evidence was
     `847232 claude 838766 Rs 00:28 php tools/run-tests.php` and
     `870665 claude 870558 R+ 00:25 php tools/run-tests.php`. I did not start
     a duplicate root run. Even if an external run finishes green, it cannot
     be acceptance evidence for an owner-free frozen snapshot while `HEAD`,
     dirty counts, and lane metadata continue to move.

3. **Critical - `porting.html` and `porting-summary.json` are stale against current lane metadata.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:2-8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, phase,
     audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes source `89260857cc71`, generated
     `2026-05-24 12:29:46 UTC`, while sampled `HEAD` is `e3de8087`.
     Current lane files report Difftastic `1058/1213` and `3624` assertions
     while the dashboard says `851/1077` and `3245`; Dolt `448` PASS cases
     while the dashboard says `425`; esbuild manifest/status `469/468` while the dashboard says
     `429`; Gitoxide `7587` assertions while the dashboard says `7152`;
     libsqlite `375/1589` and `6014` assertions while the dashboard says
     `349/1589` and `348`; LightningCSS `2967/3548` and `4291` assertions
     while the dashboard says `2765/3548` and `4065`; markerPDF `377/426`
     and `515` tests while the dashboard says `347/396` and `484`; Pandoc
     `2268/2276` and `392` tests while the dashboard says `1891/2276` and
     `362`; rclone `972` while the dashboard says `906`; Readability `3839`
     assertions while the dashboard says `3545`; and Syncthing `8911`
     assertions while the dashboard says `7902`.

4. **High - manifest/status ledgers remain non-atomic and internally inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:29`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:75`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2601`,
     `lanes/dolt/lane-status.json:5-13`,
     `lanes/lightningcss/lane-status.json:13`,
     `porting-summary.json:28-42`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by denominator, mapped
     tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: Dolt status now reports `448` PASS cases and cleared
     JSON_VALUE selector evidence, but the manifest native PHP ledger still
     says `phpBehaviorTests: 442`, and older root-harness prose still records
     stale active-root PIDs. Esbuild manifest mapped/native PHP count is
     `469`, but lane status still reports `468`. LightningCSS status still
     says `HEAD 314f357474f7 at status update` while current sampled `HEAD`
     is `e3de8087`. `porting-summary.json` is older still, with Dolt `425`,
     esbuild `429`, and stale dashboard commit `89260857cc71`.

5. **High - support-library coverage is still backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:3-22`,
     `dependency-backlog.json:81-95`,
     `dependency-backlog.json:129-175`,
     `dependency-backlog.json:179-190`,
     `dependency-backlog.json:214-267`,
     `dependency-backlog.json:322-336`,
     `dependency-backlog.json:340-409`,
     `dependency-backlog.json:413-425`,
     `dependency-backlog.json:629-645`, `porting.html:72-78`.
   - Goal requirement at risk: `goal.md:35-40` require meaningful fixture
     parity, edge-case coverage, upstream tests as source of truth, and
     explicit blockers. The latest support-library directive requires bounded
     native support components with activation gates, dependency-specific
     upstream/spec denominators, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, and bounded install-attempt notes
     where tooling is missing.
   - Evidence: the backlog visibly covers Pandoc DOC, DOCX/OpenXML, PDF
     input/output handoff, EPUB, ODT/OpenDocument, templates, citations, math,
     tables, package containers, XML/HTML, Unicode/charset, JSON/YAML
     metadata, syntax highlighting, and archive/compression, plus other shared
     support rows. It still has `0` active support rows. The root lane
     manifest set contains only the 12 base `lanes/*/UPSTREAM_TEST_MANIFEST.json`
     files; there are no accepted dependency-specific support manifests, PHP
     pass/fail ledgers, malformed/corrupt evidence records, accepted
     activation records, or bounded install-attempt notes for these rows.

6. **High - Pandoc remains far short of the original rich conversion-kernel goal despite 99 percent status language.**
   - Paths: `goal.md:12`, `lanes/pandoc/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:10-14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:389`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:391`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1394-1395`.
   - Goal requirement at risk: `goal.md:12` requires a document conversion
     kernel with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: Pandoc reports `99%`, but full upstream Haskell runner parity
     remains unexecuted. The current slice explicitly does not invoke upstream
     Pandoc, browser tooling, converter shell-outs, PDF processing,
     ZIP/package parsers, citation/CSL engines, PlainMath/MathML conversion,
     TeX math/ref conversion, or broader XML/HTML/syntax-highlighting support.
     DOC/DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument,
     citations, math, templates, tables, JSON/YAML metadata,
     archive/compression, XML/HTML, Unicode, and charset remain inactive
     support rows rather than accepted conversion-kernel coverage.

7. **High - markerPDF still mixes useful native PDF extraction with plan-only runtime/application evidence.**
   - Paths: `goal.md:9`, `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:10-14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:598`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and
     `goal.md:35-40` say wrappers, shell-outs, and plan-only application
     behavior must not count as native implementation progress.
   - Evidence: the native PDF text-object and positioned-word-gap work is
     useful and explicitly avoids Python/pdftext/pypdfium/Poppler/Ghostscript,
     but the denominator still carries benchmark archive planning, Streamlit
     app flow, FastAPI/Uvicorn server shape, Poetry/package metadata,
     OCR/model install planning, multiprocessing/chunk shell lifecycle, model
     runtime dependency graphs, and other plan-only runtime evidence. Richer
     searchable PDF, OCR/layout, and table work should be credited only
     through accepted bounded rows such as `pdf-text-dictionary-core`,
     `layout-ocr-result-core`, and `table-geometry-core`.

8. **Medium - the 98-99 percent progress claims remain misleading.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `porting-summary.json:8`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:35-40` require meaningful fixture
     parity, edge-case coverage, upstream tests as source of truth, and
     explicit blockers for hard features.
   - Evidence: the published average is `98.3%` and most lanes report
     `98-99%`, but the dashboard is stale, the current lane work is mostly
     pending or uncommitted, root acceptance is blocked by an active duplicate
     root harness and moving dirty counts, manifest/status ledgers still
     disagree, several upstream full-suite runners remain static-only or
     bounded, and no support-library row is active.

## Next Intervention

Freeze or wait out active lane workers, root/focused runners, status
publishers, dashboard publishers, evaluator/auditor loops, capacity jobs, and
integrator loops. Then require two stable dirty-count/HEAD polls with
`pgrep -af '^php tools/run-tests\.php$'` clear before any audit-owned root run.
The best next intake is still a single owner-free lane batch from a frozen
snapshot: normalize Dolt manifest/status PHP-count drift, enforce atomic
manifest/status writes, run focused verification plus `git diff --check`, run
exactly one serialized no-argument `php tools/run-tests.php` only from that
frozen snapshot, regenerate `porting.html` and `porting-summary.json` from the
accepted commit, and keep support-library rows inactive until a base-lane rich
slice is accepted or accepted-blocked on one bounded component.
