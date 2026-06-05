# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T053208Z`

Base accepted HEAD: `84ab27111aed7a1f7263c1f4f4ca36b52258db2f`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bounded catalog presentation
  preflight for fake-produced PDF bytes.
- Fake-runner results now expose `pdfPageLayout`, `pdfPageMode`,
  `pdfOpenAction`, and `pdfViewerPreferences`.
- `OpenAction` destination arrays such as `[3 0 R /FitH 720]` are summarized
  without resolving page geometry or executing a renderer.
- Viewer preference dictionaries expose common boolean, name, and integer
  fields including `DisplayDocTitle`, `HideToolbar`, `Direction`,
  `PrintScaling`, and `NumCopies`.
- `PdfEngineHandoff::fakeRunSequence()` carries final catalog presentation
  fields through multipass fake-runner summaries.
- Updated `examples/wordpress-pdf-engine-handoff.php` so the WordPress review
  packet smoke exposes catalog presentation metadata alongside existing
  sidecar, warning/error, rerun, SyncTeX, recorder, output, annotation/link/
  attachment, metadata/language, and encryption diagnostics.

## Source Truth

- Uses the accepted static Pandoc inventory plus the
  `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports a bounded PDF-output handoff diagnostic only: keep renderer-
  produced opening/viewer state visible to WordPress review queues without
  executing Pandoc, TeX, Typst, browser, roff, or PDF engines.
- It does not implement destination resolution, named-destination trees, page
  geometry interpretation, JavaScript/open-action execution, full xref/object-
  stream parsing, stream decompression, PDF/A validation, or renderer sandbox
  execution.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 286 assertions, 0 failures.

Red-first focused check before completing implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed: 1 test file, 286 assertions, 1 failure. The new catalog presentation
  test failed because `parsePdfArray()` was absent for `/OpenAction
  [3 0 R /FitH 720]`.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 300 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.
- `php -l lanes/pandoc/src/PdfEngineHandoff.php`,
  `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  passed: `lane-status json ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'`
  passed: `manifest json ok`.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/
resource source handoff, source-artifact validation, resource-file validation,
expected TeX sidecar inventory, engine warning/error log extraction, missing
renderer executable triage, bibliography sidecar classification, generated PDF
output byte/path/page metrics, produced PDF page-tree/outline inspection,
produced PDF document-info/language inspection, produced PDF annotation/link/
embedded-file inspection, produced PDF encryption/permission preflight,
SyncTeX/source-map extraction, TeX recorder `.fls` dependency parsing, TeX
transcript include-graph parsing, or multipass rerun-state aggregation.

The new surface is produced-PDF catalog presentation metadata: page layout,
page mode, open-action destination summary, and viewer preferences from fake-
produced bytes.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion,
charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB
behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted fake-runner
file-map/result contract. Real PDF rendering, named destination resolution,
document JavaScript execution, full cross-reference/object-stream parsing,
stream decompression, real executable discovery, real `.fls` generation, real
SyncTeX generation, real bibliography execution, and remote resource fetching
remain intentionally out of scope.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.
