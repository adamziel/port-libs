# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T081143Z`

Base accepted HEAD: `3f2f284dba0aeb0eaa9ef2113ebfb3341fdf9e8e`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bounded produced-PDF
  `/OutputIntents` preflight from fake-runner PDF bytes.
- Fake-runner results now expose `pdfOutputIntents` with output-intent type,
  PDF/A or PDF/X subtype, output-condition identifier/description, registry,
  info text, destination ICC profile reference, profile component count,
  alternate color space, profile byte count, and unfiltered profile hash.
- `PdfEngineHandoff::fakeRunSequence()` now carries the final run output
  intents as `finalPdfOutputIntents`.
- Updated `examples/wordpress-pdf-engine-handoff.php` so WordPress review
  queues can inspect produced-PDF color/output-intent evidence alongside the
  existing sidecar, log, bibliography, SyncTeX, trailer, page, form,
  annotation, attachment, document-info, XMP/PDF-A, catalog, and encryption
  diagnostics.

## Source Truth

- Uses the accepted static Pandoc inventory plus the
  `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports a bounded PDF-output handoff diagnostic only: expose renderer-
  produced output intent and ICC profile handoff metadata to WordPress review
  queues without executing Pandoc, TeX, Typst, browser, roff, PDF engines,
  external PDF validators, external XML/PDF tools, or online services.
- It does not implement compressed ICC profile stream decoding, PDF/A
  conformance validation, output-intent color-management validation,
  tagged-PDF structure trees, named-destination trees, JavaScript/additional-
  action preflight, object-stream parsing, xref repair, stream decryption, or
  renderer sandbox execution.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 336 assertions, 0 failures.

Red-first focused check while implementing:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed: 1 test file, 338 assertions, 1 failure. The new output-intent test
  exposed that `pdfOutputIntents` was not yet present in fake-runner results.

Focused verification after implementation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php` passed.
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 342 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  passed: `pandoc json ok`.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Mapping Delta

- `PdfEngineHandoffTest.php` coverage increased from 336 to 342 assertions.
- `lane-status.json` `phpPass` moved from 766 to 767.
- `UPSTREAM_TEST_MANIFEST.json` mapped checks moved from 1,225 to 1,226.
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
inspection, XMP/PDF-A metadata extraction, catalog presentation inspection,
annotation/link/embedded-file inspection, AcroForm field extraction,
encryption/permission preflight, SyncTeX/source-map extraction, TeX recorder
`.fls` dependency parsing, TeX transcript include-graph parsing, or multipass
rerun-state aggregation.

The new surface is bounded produced-PDF output intent and ICC profile metadata
from fake-runner bytes.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion,
charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB
behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted fake-runner
file-map/result contract. Compressed ICC profiles, color-management
validation, full PDF/A validation, tagged-PDF structure trees, named
destinations, full cross-reference/object-stream parsing, stream decryption,
real executable discovery, real `.fls` generation, real SyncTeX generation,
real bibliography execution, and remote resource fetching remain intentionally
out of scope.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.
