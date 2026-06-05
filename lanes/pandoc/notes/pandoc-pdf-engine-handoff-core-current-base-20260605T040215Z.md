# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T040215Z`

Base accepted HEAD: `c6df8509d870cc109fe6574dd1c13935282c8805`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bounded inspection of small fake-produced PDF byte artifacts for annotation and embedded-file review metadata.
- Fake-runner results now expose `pdfAnnotationTypes`, `pdfLinkTargets`, and `pdfEmbeddedFileNames`.
- Annotation subtype counts are limited to known PDF annotation subtypes; URI link targets are extracted from `/Subtype /Link` or `/S /URI` dictionaries; embedded-file names are extracted from `/Filespec` `/F`/`/UF` strings and `/EmbeddedFiles` name-tree entries.
- `PdfEngineHandoff::fakeRunSequence()` now carries the final annotation, URI target, and embedded-file fields through multipass fake-runner summaries.
- Updated `examples/wordpress-pdf-engine-handoff.php` so the WordPress review-packet smoke exposes link and file-attachment preflight metadata alongside existing resource, log, bibliography, recorder, SyncTeX, output, and transcript diagnostics.

## Source Truth

- Uses the accepted static Pandoc inventory plus the `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports a bounded PDF-output handoff diagnostic only: keep link and embedded-file surfaces visible to WordPress review queues without executing Pandoc, TeX, Typst, browser, roff, or PDF engines.
- It does not implement full PDF parsing, PDF rendering, annotation appearance handling, file-attachment byte extraction, remote link fetching, PDF encryption handling, or renderer sandbox execution.

## Verification

Baseline focused check before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 250 assertions, 0 failures.

Red-first focused check before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed: 1 test file, 252 assertions, 1 failure. The new annotation/link/embedded-file test failed because `pdfAnnotationTypes` was not present in fake-runner results.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 261 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.
- `php -l lanes/pandoc/src/PdfEngineHandoff.php`,
  `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/resource source handoff, source-artifact validation, resource-file validation, expected TeX sidecar inventory, engine warning/error log extraction, missing renderer executable triage, bibliography sidecar classification, generated PDF output byte/path/page metrics, produced PDF page-tree/outline inspection, SyncTeX/source-map extraction, TeX recorder `.fls` dependency parsing, TeX transcript include-graph parsing, or multipass rerun-state aggregation.

The new surface is produced-PDF annotation subtype, URI link target, and embedded-file name preflight from fake-produced bytes.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT, EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion, charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native PHP `PdfEngineHandoff` support component and reuses the accepted fake-runner file-map/result contract. Real PDF rendering, full cross-reference/object-stream parsing, annotation appearance parsing, embedded-file byte extraction, real executable discovery, real `.fls` generation, real SyncTeX generation, real bibliography execution, and remote resource fetching remain intentionally out of scope.

Full upstream Pandoc runner parity remains blocked by the missing hydrated Pandoc checkout and Haskell Cabal dependency closure already recorded in lane status.
