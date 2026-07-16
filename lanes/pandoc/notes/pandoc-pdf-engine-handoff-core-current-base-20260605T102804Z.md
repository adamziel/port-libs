# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T102804Z`

Base accepted HEAD: `a9cebd57807e3031e88aec3c70c5f43228a19ef7`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bounded produced-PDF page-label
  range extraction from catalog `/PageLabels` number trees.
- Fake-runner results now expose `pdfPageLabels` with zero-based page indices,
  one-based page numbers, `/S` style names, decoded `/P` prefixes, `/St`
  start numbers, first-label previews, and source number-tree paths.
- `PdfEngineHandoff::fakeRunSequence()` now carries the final run page-label
  summary as `finalPdfPageLabels`.
- Updated `examples/wordpress-pdf-engine-handoff.php` so WordPress review
  queues can inspect produced-PDF page labels alongside existing sidecar, log,
  bibliography, SyncTeX, trailer, page geometry, outline, named destination,
  catalog, tagging, annotation/form, active-action, XMP/PDF-A, output-intent,
  and encryption diagnostics.

## Source Truth

- Uses the accepted static Pandoc inventory plus the
  `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports a bounded PDF-output handoff diagnostic only: expose renderer-
  produced `/PageLabels` range metadata to WordPress review queues without
  executing Pandoc, TeX/PDF engines, Typst, browser renderers, roff renderers,
  JavaScript, external PDF validators, or online services.
- It does not implement full per-page label expansion, page-label inheritance
  validation, xref repair, object-stream parsing, stream filter decoding,
  stream decryption, PDF/A validation, or renderer sandbox execution.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 365 assertions, 0 failures.

Red-first check during implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed at 367 assertions because page-label prefixes were trimmed; `/P`
  prefixes can intentionally end with a space, so the page-label parser now
  preserves decoded prefix text instead of using the generic trimmed metadata
  helper.

Focused verification after implementation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php` passed.
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 370 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  passed: `pandoc json ok`.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Mapping Delta

- `PdfEngineHandoffTest.php` coverage increased from 365 to 370 assertions.
- `lane-status.json` `phpPass` moved from 832 to 833.
- `UPSTREAM_TEST_MANIFEST.json` mapped checks moved from 1,292 to 1,293.
- `pdfEngineHandoffCoreCases`, `mappedPdfEngineHandoffCoreCases`, and
  `pdfEngineHandoffCoreAssertions` moved from `10 / 10 / 95` to
  `11 / 11 / 100`.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/
resource source handoff, source-artifact validation, resource-file validation,
expected TeX sidecar inventory, engine warning/error log extraction, missing
renderer executable triage, bibliography sidecar classification, generated PDF
output byte/path/page metrics, PDF trailer revision metadata, page-tree/
outline inspection, page boxes and rotations, document-info and language
inspection, XMP/PDF-A metadata extraction, output-intent metadata extraction,
catalog presentation inspection, annotation/link/embedded-file inspection,
AcroForm field extraction, active-action and JavaScript hash preflight, named
destination extraction, tagged-structure metadata extraction,
encryption/permission preflight, SyncTeX/source-map extraction, TeX recorder
`.fls` dependency parsing, TeX transcript include-graph parsing, or multipass
rerun-state aggregation.

The new surface is bounded produced-PDF page-label range metadata from
catalog `/PageLabels` number trees.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion,
charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB
behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted fake-runner
file-map/result contract. Full page-label expansion per page, page-label
inheritance validation, xref repair, object streams, stream filters, stream
decryption, real executable discovery, real `.fls` generation, real SyncTeX
generation, real bibliography execution, JavaScript execution, PDF/A
validation, and remote resource fetching remain intentionally out of scope.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.
