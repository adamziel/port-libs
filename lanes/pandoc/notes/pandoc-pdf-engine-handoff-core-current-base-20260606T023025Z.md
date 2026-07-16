# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260606T023025Z`

Base accepted HEAD: `8939543119a291af01b67d59e9e9d7db95241345`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bounded produced-PDF catalog
  URI-base metadata from `/URI << /Base (...) >>` dictionaries.
- Fake-runner results now expose `pdfUriBase` and add a
  `pdf-byte-uri-base:*` diagnostic when produced PDF bytes declare a catalog
  URI base.
- `PdfEngineHandoff::fakeRunSequence()` now carries the final run URI-base
  metadata as `finalPdfUriBase`.
- Updated the WordPress PDF review-packet smoke so import queues can audit
  relative PDF link targets against the produced document base without running
  a renderer.

## Source Truth

- Uses the accepted static Pandoc inventory plus the
  `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports a bounded PDF-output handoff diagnostic only: expose produced-PDF
  catalog URI-base metadata to WordPress review queues without executing
  Pandoc, TeX/PDF engines, Typst, browser renderers, roff, external PDF
  validators, JavaScript, online sanitizers, online services, or live provider
  tests.
- It does not implement compressed destination/object-stream parsing, xref
  repair, full URI resolution, stream decryption, PDF/A validation, or renderer
  sandbox execution.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 553 assertions, 0 failures.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 559 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Mapping Delta

- `PdfEngineHandoffTest.php` coverage increased from 553 to 559 assertions.
- `lane-status.json` `phpPass` moved from 1,156 to 1,157.
- `UPSTREAM_TEST_MANIFEST.json` mapped checks moved from 1,606 to 1,607.
- `pdfEngineHandoffCoreCases`, `mappedPdfEngineHandoffCoreCases`, and
  `pdfEngineHandoffCoreAssertions` moved from `10 / 10 / 95` to
  `11 / 11 / 101`.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/
resource source handoff, source-artifact validation, resource-file validation,
expected TeX sidecar inventory, engine warning/error log extraction, missing
renderer executable triage, bibliography sidecar classification, generated PDF
output byte/path/page metrics, PDF trailer revision metadata, xref/object
stream metadata, page-tree/outline inspection, page boxes and rotations,
page labels, page timings/transitions, font/image/form-XObject resources,
document-info and language inspection, XMP/PDF-A metadata extraction, page
metadata, piece-info metadata, output-intent metadata extraction, catalog
presentation/viewer preferences, named destinations, tagged-PDF structure
metadata, annotation/link/embedded-file inspection, AcroForm extraction,
collection/thread/signature metadata, optional-content layers, active-action
and JavaScript hash preflight, encryption/permission preflight, SyncTeX/
source-map extraction, TeX recorder `.fls` dependency parsing, TeX transcript
include-graph parsing, or multipass rerun-state aggregation.

The new surface is bounded produced-PDF catalog URI-base metadata extraction
from fake-runner bytes.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion,
charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB
behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted fake-runner
file-map/result contract. Compressed destination streams, object streams, xref
repair, full URI resolution, full PDF/A validation, stream decryption, real
executable discovery, real `.fls` generation, real SyncTeX generation, real
bibliography execution, JavaScript execution, and remote resource fetching
remain intentionally out of scope.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.
