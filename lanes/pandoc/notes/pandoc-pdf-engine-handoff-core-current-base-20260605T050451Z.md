# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T050451Z`

Base accepted HEAD: `735fe503b9c6b5bba2b618c4dcf8b897ba1ab080`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bounded produced-PDF metadata preflight.
- Fake-runner results now expose `pdfDocumentInfo` for `/Info` dictionary fields `Title`, `Author`, `Subject`, `Keywords`, `Creator`, `Producer`, `CreationDate`, `ModDate`, and `Trapped`.
- Fake-runner results now expose `pdfLanguage` from the PDF catalog `/Lang` entry.
- `PdfEngineHandoff::fakeRunSequence()` carries `finalPdfDocumentInfo` and `finalPdfLanguage` through multipass fake-runner summaries.
- Updated `examples/wordpress-pdf-engine-handoff.php` so the WordPress review-packet smoke exposes document-info and language metadata alongside the existing sidecar, warning/error, rerun, SyncTeX, recorder, output, annotation/link/attachment, and encryption diagnostics.

## Source Truth

- Uses the accepted static Pandoc inventory plus the `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports a bounded PDF-output handoff diagnostic only: keep renderer-produced metadata visible to WordPress review queues without executing Pandoc, TeX, Typst, browser, roff, or PDF engines.
- It does not implement XMP packet parsing, tagged-PDF structure trees, PDF/A validation, object-stream parsing, xref repair, stream decompression, remote link fetching, or renderer sandbox execution.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 278 assertions, 0 failures.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 286 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 20 test files, 7284 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '`
  passed: `636`.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.
- `php -l lanes/pandoc/src/PdfEngineHandoff.php`, `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`, and `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` passed.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'` passed.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/resource source handoff, source-artifact validation, resource-file validation, expected TeX sidecar inventory, engine warning/error log extraction, missing renderer executable triage, bibliography sidecar classification, generated PDF output byte/path/page metrics, produced PDF page-tree/outline inspection, produced PDF annotation/link/embedded-file inspection, produced PDF encryption/permission preflight, SyncTeX/source-map extraction, TeX recorder `.fls` dependency parsing, TeX transcript include-graph parsing, or multipass rerun-state aggregation.

The new surface is produced-PDF document-info metadata and catalog-language preflight from fake-produced bytes.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT, EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion, charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native PHP `PdfEngineHandoff` support component and reuses the accepted fake-runner file-map/result contract. Real PDF rendering, XMP metadata packet parsing, tagged-PDF structure-tree inspection, PDF/A validation, full cross-reference/object-stream parsing, stream decompression, real executable discovery, real `.fls` generation, real SyncTeX generation, real bibliography execution, and remote resource fetching remain intentionally out of scope.

Full upstream Pandoc runner parity remains blocked by the missing hydrated Pandoc checkout and Haskell Cabal dependency closure already recorded in lane status.
