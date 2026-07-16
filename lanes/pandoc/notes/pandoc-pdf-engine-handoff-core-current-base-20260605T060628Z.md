# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T060628Z`

Base accepted HEAD: `a554ec7ad7a3cd881f170989ea7f07abd9f4a486`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bounded AcroForm field metadata
  extraction from fake-produced PDF bytes.
- Fake-runner results now expose `pdfFormFields` and `pdfFormFieldTypes` for
  `/AcroForm /Fields` references, covering field name, `/FT` type, type label,
  alternate name, mapping name, value, default value, field flags, decoded flag
  names, and choice options.
- `PdfEngineHandoff::fakeRunSequence()` carries `finalPdfFormFields` and
  `finalPdfFormFieldTypes` through multipass fake-runner summaries.
- Updated `examples/wordpress-pdf-engine-handoff.php` so the WordPress review
  packet smoke exposes a form-bearing PDF handoff alongside existing resource,
  sidecar, log, bibliography, SyncTeX, output, metadata, presentation,
  annotation, attachment, and encryption diagnostics.

## Source Truth

- Uses the accepted static Pandoc inventory plus the
  `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports a bounded PDF-output handoff diagnostic only: keep renderer-
  produced form metadata visible to WordPress review queues without executing
  Pandoc, TeX, Typst, browser, roff, or PDF engines.
- It does not implement form appearance stream rendering, default resource
  interpretation, JavaScript actions, full field inheritance, xref/object-stream
  recovery, stream decompression, PDF/A validation, or renderer sandbox
  execution.

## Verification

Baseline focused check before this slice, from the accepted previous PDF note:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 300 assertions, 0 failures.

Red-first focused check before completing implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed: 1 test file, 302 assertions, 1 failure. The new AcroForm test failed
  because `fakeRun()` did not expose `pdfFormFieldTypes`.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 308 assertions, 0 failures.
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
produced PDF document-info/language inspection, produced PDF catalog
presentation inspection, produced PDF annotation/link/embedded-file inspection,
produced PDF encryption/permission preflight, SyncTeX/source-map extraction,
TeX recorder `.fls` dependency parsing, TeX transcript include-graph parsing,
or multipass rerun-state aggregation.

The new surface is produced-PDF AcroForm field metadata from bounded fake-
produced bytes.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion,
charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB
behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted fake-runner
file-map/result contract. Real PDF rendering, full AcroForm appearance parsing,
default resource interpretation, tagged-PDF structure-tree inspection, XMP
metadata parsing, full cross-reference/object-stream parsing, stream
decompression, real executable discovery, real `.fls` generation, real SyncTeX
generation, real bibliography execution, and remote resource fetching remain
intentionally out of scope.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.
