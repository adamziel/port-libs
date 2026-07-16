# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T091822Z`

Base accepted HEAD: `fc832a46164b6beed08847bc9302047fb42572bd`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bounded produced-PDF named
  destination preflight.
- Fake-runner results now expose `pdfNamedDestinations` for modern catalog
  `/Names /Dests` name trees and legacy catalog `/Dests` dictionaries.
- `PdfEngineHandoff::fakeRunSequence()` carries the final run destination
  preflight as `finalPdfNamedDestinations`.
- Updated `examples/wordpress-pdf-engine-handoff.php` so WordPress review
  packets expose renderer-produced internal PDF anchor targets alongside the
  existing sidecar, log, bibliography, SyncTeX, trailer, page, form,
  annotation, attachment, active-action, XMP/PDF-A, output-intent, catalog,
  and encryption diagnostics.

## Source Truth

- Uses the accepted static Pandoc inventory plus the
  `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports a bounded PDF-output handoff diagnostic only: expose produced-PDF
  destination metadata to WordPress review queues without executing Pandoc,
  TeX/PDF engines, Typst, browser renderers, roff, PDF validators, JavaScript,
  or online services.
- It does not implement compressed destination streams, object-stream parsing,
  cross-reference repair, tagged-PDF structure trees, named-destination link
  validation, stream decryption, PDF/A validation, or renderer sandbox
  execution.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 351 assertions, 0 failures.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 356 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.
- `php -l lanes/pandoc/src/PdfEngineHandoff.php`,
  `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  passed: `pandoc json ok`.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Mapping Delta

- `PdfEngineHandoffTest.php` coverage increased from 351 to 356 assertions.
- `lane-status.json` `phpPass` moved from 800 to 801.
- `UPSTREAM_TEST_MANIFEST.json` mapped checks moved from 1,260 to 1,261.
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
AcroForm field extraction, active-action and JavaScript hash preflight,
encryption/permission preflight, SyncTeX/source-map extraction, TeX recorder
`.fls` dependency parsing, TeX transcript include-graph parsing, or multipass
rerun-state aggregation.

The new surface is bounded produced-PDF named destination extraction from
fake-runner bytes.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion,
charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB
behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted fake-runner
file-map/result contract. Compressed destination streams, object streams,
xref repair, tagged-PDF structure trees, full PDF/A validation, stream
decryption, real executable discovery, real `.fls` generation, real SyncTeX
generation, real bibliography execution, JavaScript execution, and remote
resource fetching remain intentionally out of scope.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.
