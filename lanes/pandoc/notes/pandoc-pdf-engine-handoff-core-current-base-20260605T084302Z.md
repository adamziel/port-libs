# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T084302Z`

Base accepted HEAD: `49bb8d5ee69f0b05c590c10ef609ae999145379e`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bounded produced-PDF active
  action preflight from fake-produced PDF bytes.
- Fake-runner results now expose `pdfActiveActions` and
  `pdfActiveActionTypes` for catalog `/OpenAction`, document JavaScript name
  trees, `/AA` additional actions, page actions, annotation actions, launch
  targets, submit-form targets, and JavaScript byte hashes.
- `PdfEngineHandoff::fakeRunSequence()` carries the final run action preflight
  as `finalPdfActiveActions` and `finalPdfActiveActionTypes`.
- Narrowed embedded-file name extraction to the `/EmbeddedFiles` name subtree
  so document-level JavaScript names are not misclassified as attachments.
- Updated `examples/wordpress-pdf-engine-handoff.php` so WordPress review
  queues can inspect active-content metadata alongside existing sidecar, log,
  bibliography, SyncTeX, trailer, page, form, annotation, attachment,
  document-info, XMP/PDF-A, output-intent, catalog, and encryption diagnostics.

## Source Truth

- Uses the accepted static Pandoc inventory plus the
  `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports a bounded PDF-output handoff diagnostic only: expose renderer-
  produced active action metadata to WordPress review queues without executing
  Pandoc, TeX, Typst, browser, roff, PDF engines, PDF validators, JavaScript,
  or online services.
- It does not implement tagged-PDF structure trees, named-destination trees,
  compressed JavaScript stream decoding, JavaScript execution, PDF/A
  validation, object-stream parsing, xref repair, stream decryption, or
  renderer sandbox execution.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 342 assertions, 0 failures.

Red-first focused check while implementing:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed: 1 test file, 344 assertions, 1 failure. The new active-action test
  exposed that JavaScript name-tree entries were not yet parsed.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 351 assertions, 0 failures.
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

- `PdfEngineHandoffTest.php` coverage increased from 342 to 351 assertions.
- `lane-status.json` `phpPass` moved from 785 to 786.
- `UPSTREAM_TEST_MANIFEST.json` mapped checks moved from 1,245 to 1,246.
- `pdfEngineHandoffCoreCases`, `mappedPdfEngineHandoffCoreCases`, and
  `pdfEngineHandoffCoreAssertions` moved from `10 / 10 / 95` to
  `11 / 11 / 104`.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/
resource source handoff, source-artifact validation, resource-file validation,
expected TeX sidecar inventory, engine warning/error log extraction, missing
renderer executable triage, bibliography sidecar classification, generated PDF
output byte/path/page metrics, PDF trailer revision metadata, page-tree/
outline inspection, page boxes and rotations, document-info and language
inspection, XMP/PDF-A metadata extraction, output-intent metadata extraction,
catalog presentation inspection, annotation/link/embedded-file inspection,
AcroForm field extraction, encryption/permission preflight, SyncTeX/source-map
extraction, TeX recorder `.fls` dependency parsing, TeX transcript include-
graph parsing, or multipass rerun-state aggregation.

The new surface is bounded produced-PDF active action preflight and JavaScript
payload hashing from fake-runner bytes.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion,
charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB
behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted fake-runner
file-map/result contract. Compressed JavaScript streams, tagged-PDF structure
trees, named destinations, full PDF/A validation, full cross-reference/object-
stream parsing, stream decryption, real executable discovery, real `.fls`
generation, real SyncTeX generation, real bibliography execution, JavaScript
execution, and remote resource fetching remain intentionally out of scope.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.
