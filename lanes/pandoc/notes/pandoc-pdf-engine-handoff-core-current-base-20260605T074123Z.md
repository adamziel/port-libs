# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T074123Z`

Base accepted HEAD: `77f7b54408a215b8868ef1c3927a9ab284ffa262`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bounded produced-PDF XMP
  metadata preflight from uncompressed catalog `/Metadata` XML streams.
- Fake-runner results now expose `pdfXmpMetadata` with packet byte/hash
  provenance plus Dublin Core title/creator/description/format, XMP Basic
  creator/date fields, xmpMM document IDs, and PDF/A part/conformance
  identification.
- `PdfEngineHandoff::fakeRunSequence()` now carries the final run XMP summary
  as `finalPdfXmpMetadata`.
- Updated `examples/wordpress-pdf-engine-handoff.php` so WordPress review
  queues can inspect fake-produced PDF metadata packets alongside the existing
  sidecar, log, bibliography, SyncTeX, trailer, page, form, annotation,
  attachment, document-info, catalog, and encryption diagnostics.

## Source Truth

- Uses the accepted static Pandoc inventory plus the
  `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports a bounded PDF-output handoff diagnostic only: expose renderer-
  produced XMP/PDF-A metadata to WordPress review queues without executing
  Pandoc, TeX, Typst, browser, roff, PDF engines, PDF validators, XML services,
  or online services.
- It does not implement filtered/compressed XMP stream decoding, broader XMP
  schema extraction, tagged-PDF structure trees, named-destination tree
  resolution, PDF/A validation, output-intent ICC/profile inspection,
  JavaScript/additional-action preflight, object-stream parsing, xref repair,
  stream decryption, or renderer sandbox execution.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 330 assertions, 0 failures.

Red-first focused check while implementing:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed: 1 test file, 332 assertions, 1 failure. The new XMP test exposed
  that XMP element matching treated `rdf:Description` as `dc:description`
  because the helper was case-insensitive.

Focused verification after implementation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php` passed.
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 336 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  passed: `pandoc json ok`.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Mapping Delta

- `PdfEngineHandoffTest.php` coverage increased from 330 to 336 assertions.
- `lane-status.json` `phpPass` moved from 752 to 753.
- `UPSTREAM_TEST_MANIFEST.json` mapped checks moved from 1,211 to 1,212.
- `pdfEngineHandoffCoreCases`, `mappedPdfEngineHandoffCoreCases`, and
  `pdfEngineHandoffCoreAssertions` moved from `10 / 10 / 95` to
  `11 / 11 / 101`.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/
resource source handoff, source-artifact validation, resource-file validation,
expected TeX sidecar inventory, engine warning/error log extraction, missing
renderer executable triage, bibliography sidecar classification, generated PDF
output byte/path/page metrics, PDF trailer revision metadata, page-tree/
outline inspection, page boxes and rotations, document-info and language
inspection, catalog presentation inspection, annotation/link/embedded-file
inspection, AcroForm field extraction, encryption/permission preflight,
SyncTeX/source-map extraction, TeX recorder `.fls` dependency parsing, TeX
transcript include-graph parsing, or multipass rerun-state aggregation.

The new surface is bounded uncompressed produced-PDF XMP metadata and PDF/A
identification from fake-runner bytes.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion,
charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB
behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted fake-runner
file-map/result contract. Filtered/compressed XMP streams, tagged-PDF
structure trees, named destinations, PDF/A validation, output intents, full
cross-reference/object-stream parsing, stream decryption, real executable
discovery, real `.fls` generation, real SyncTeX generation, real bibliography
execution, and remote resource fetching remain intentionally out of scope.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.
