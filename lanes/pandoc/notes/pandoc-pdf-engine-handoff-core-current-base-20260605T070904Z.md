# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T070904Z`

Base accepted HEAD: `96835b31f0b7d31c68967e2c8b5127f6a9eff04e`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bounded produced-PDF page geometry
  diagnostics from fake-runner output bytes.
- Fake-runner results now expose `pdfPageBoxes` and `pdfPageRotations`,
  including inherited `/Pages` `/MediaBox`, `/CropBox`, and `/Rotate` values,
  direct page `/BleedBox`, `/TrimBox`, `/ArtBox`, and normalized negative
  rotations.
- `PdfEngineHandoff::fakeRunSequence()` now carries the final run page geometry
  as `finalPdfPageBoxes` and `finalPdfPageRotations`.
- Updated the WordPress PDF review-packet smoke so import queues can inspect
  page boxes and rotations alongside existing sidecar, transcript, trailer,
  outline, annotation, form, and encryption diagnostics.

## Source Truth

- Uses the accepted static Pandoc inventory plus the
  `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports a bounded PDF-output handoff diagnostic only: expose renderer-
  produced page geometry to WordPress review queues without executing Pandoc,
  TeX, Typst, browser, roff, or PDF engines.
- It does not implement xref repair, object streams, compressed stream
  decoding, content stream layout, tagged-PDF structure trees, PDF/A
  validation, or renderer sandbox execution.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 322 assertions, 0 failures.

Focused verification after implementation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`: passed.
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`: passed.
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`: passed.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 330 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  passed: `pandoc json ok`.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Mapping Delta

- `PdfEngineHandoffTest.php` coverage increased from 322 to 330 assertions.
- `lane-status.json` `phpPass` moved from 734 to 735.
- `UPSTREAM_TEST_MANIFEST.json` mapped checks moved from 1,193 to 1,194.
- `pdfEngineHandoffCoreCases`, `mappedPdfEngineHandoffCoreCases`, and
  `pdfEngineHandoffCoreAssertions` moved from `10 / 10 / 95` to
  `11 / 11 / 103`.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/
resource source handoff, source-artifact validation, resource-file validation,
expected TeX sidecar inventory, engine warning/error log extraction, missing
renderer executable triage, bibliography sidecar classification, generated PDF
output byte/path/page metrics, page-tree/outline inspection, document-info and
language inspection, catalog presentation inspection, annotation/link/
embedded-file inspection, AcroForm field extraction, encryption/permission
preflight, PDF trailer revision metadata, SyncTeX/source-map extraction, TeX
recorder `.fls` dependency parsing, TeX transcript include-graph parsing, or
multipass rerun-state aggregation.

The new surface is bounded produced-PDF page boxes and rotations from fake-
runner bytes.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion,
charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB
behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted fake-runner
file-map/result contract.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.
