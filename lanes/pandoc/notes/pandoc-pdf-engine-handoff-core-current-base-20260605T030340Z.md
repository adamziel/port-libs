# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T030340Z`

Base accepted HEAD: `48d21ef8b02808968c20d1389bc1e396f6afa5c7`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bounded produced-PDF byte
  inspection that does not execute any renderer.
- Fake-runner results now expose `pdfPageCount` from `/Type /Pages /Count`
  page trees, with a page-object count fallback for small fake-produced PDF
  artifacts.
- Fake-runner results now expose flat `pdfOutlineTitles` from outline item
  `/Title` values, including PDF literal strings and UTF-16BE hex strings.
- A renderer log page-count declaration that disagrees with the produced PDF
  bytes now fails as `pdf-output-page-mismatch` and records
  `engine-output-page-mismatch:<declared>:<actual>`.
- Extended `PdfEngineHandoff::fakeRunSequence()` to preserve final PDF page
  count and outline titles for multipass WordPress review packets.
- Updated `examples/wordpress-pdf-engine-handoff.php` so the smoke packet
  exposes produced-byte page and outline metadata alongside the existing
  resource, bibliography, recorder, SyncTeX, missing-engine, log, and rerun
  diagnostics.

## Source Truth

- Uses the accepted static Pandoc inventory plus the
  `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports a bounded fake-runner handoff for produced PDF artifact metadata:
  keep page-count and outline title evidence available for WordPress import
  triage while rejecting stale renderer transcript page counts.
- It does not implement or invoke Pandoc, TeX/PDF engines, Typst, browser
  layout/rendering, roff, BibTeX, Biber, external template engines, Haskell
  test binaries, archive tools, office tools, or online services.

## Verification

Baseline focused check before adding the red test:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 226 assertions, 0 failures.

Red-first focused check before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed: 1 test file, 228 assertions, 1 failure. The new produced-byte
  metadata test failed because `pdfPageCount` was not present.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 237 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 19 test files, 6127 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  passed: `560`.
- `php -l lanes/pandoc/src/PdfEngineHandoff.php`,
  `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  passed: `pandoc json ok`.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/
resource source handoff, source-artifact validation, expected ordinary TeX
sidecar inventory, engine warning/error log extraction, missing renderer
executable triage, bibliography sidecar classification, generated PDF output
byte/path metric validation, resource-file validation, SyncTeX/source-map
extraction, TeX recorder `.fls` dependency parsing, or multipass rerun-state
aggregation. The new surface is produced-PDF byte page-tree count inspection,
flat outline title extraction, page-count mismatch rejection, and final
multipass metadata handoff.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion,
charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB
behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted fake-runner
file-map/result contract. Real PDF rendering, full PDF parsing, outline
hierarchy/named destination resolution, real executable discovery, real `.fls`
generation, real SyncTeX generation, real bibliography execution, and remote
resource fetching remain intentionally out of scope.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.
