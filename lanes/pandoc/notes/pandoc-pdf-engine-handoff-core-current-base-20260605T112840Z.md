# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T112840Z`

Base accepted HEAD: `2196c6ddecaf1419161d7b0fab7179dfbe81bc4f`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bounded produced-PDF font
  resource preflight from fake-produced PDF bytes.
- Fake-runner results now expose `pdfFonts` with page/resource names,
  inherited page `/Resources`, font object references, subtypes, base fonts,
  encodings, ToUnicode references, descendant fonts, font descriptors,
  embedded font stream hashes, and filtered/too-large skip reasons.
- Fake-runner results now expose `pdfFontSubtypes`, and
  `PdfEngineHandoff::fakeRunSequence()` carries `finalPdfFonts` and
  `finalPdfFontSubtypes`.
- Updated `examples/wordpress-pdf-engine-handoff.php` so WordPress PDF review
  packets include produced-PDF font diagnostics alongside existing sidecar,
  log, bibliography, SyncTeX, trailer, page geometry, page labels, outlines,
  named destinations, tagging, active-action, annotation/form, attachment,
  XMP/PDF-A, output-intent, catalog, and encryption diagnostics.

## Source Truth

- Uses the accepted static Pandoc inventory plus upstream Pandoc
  `Text.Pandoc.PDF.makePDF` behavior at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`: Pandoc writes an intermediate
  source, delegates PDF production to configured LaTeX/HTML/Typst/roff
  engines, then consumes produced PDF/log bytes.
- This ports a bounded PDF-output handoff diagnostic only: expose renderer-
  produced font resource and embedded-font metadata to WordPress review queues
  without executing Pandoc, TeX/PDF engines, Typst, browser renderers, roff
  renderers, PDF validators, JavaScript, or online services.
- It does not implement full font subsetting validation, ToUnicode CMap
  decoding, glyph coverage, font license flag interpretation, compressed font
  stream decoding, object-stream parsing, xref repair, stream decryption,
  PDF/A validation, or renderer sandbox execution.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 379 assertions, 0 failures.

Red-first focused check while implementing:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed: 1 test file, 381 assertions, 1 failure. The new font-resource test
  exposed that `pdfFonts` was not yet present in fake-runner results.

Focused verification after implementation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php` passed.
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 389 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  passed: `pandoc json ok`.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Mapping Delta

- `PdfEngineHandoffTest.php` coverage increased from 379 to 389 assertions.
- `lane-status.json` `phpPass` moved from 866 to 867.
- `UPSTREAM_TEST_MANIFEST.json` mapped checks moved from 1,324 to 1,325.
- `pdfEngineHandoffCoreCases`, `mappedPdfEngineHandoffCoreCases`, and
  `pdfEngineHandoffCoreAssertions` moved from `10 / 10 / 95` to
  `11 / 11 / 105`.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/
resource source handoff, source-artifact validation, resource-file validation,
expected TeX sidecar inventory, engine warning/error log extraction, missing
renderer executable triage, bibliography sidecar classification, generated PDF
output byte/path/page metrics, PDF trailer revision metadata, page-tree/
outline inspection, page boxes and rotations, page labels, document-info and
language inspection, XMP/PDF-A metadata extraction, output-intent metadata
extraction, catalog presentation inspection, annotation/link/embedded-file
inspection, embedded file stream metadata, AcroForm field extraction, active-
action and JavaScript hash preflight, named destination extraction, tagged-
structure metadata extraction, encryption/permission preflight, SyncTeX/source-
map extraction, TeX recorder `.fls` dependency parsing, TeX transcript include-
graph parsing, or multipass rerun-state aggregation.

The new surface is bounded produced-PDF page font resource, descriptor, and
embedded font stream metadata from fake-runner bytes.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion,
charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB
behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted fake-runner
file-map/result contract plus bounded PDF dictionary parsing. Full font
subsetting validation, ToUnicode CMap decoding, glyph coverage checks,
compressed font stream decoding, font license flag interpretation, object
streams, xref repair, stream decryption, real executable discovery, real
`.fls` generation, real SyncTeX generation, real bibliography execution,
JavaScript execution, PDF/A validation, and remote resource fetching remain
intentionally out of scope.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.
