# Pandoc PDF Engine Handoff Core Current Base 20260605T115857Z

Base accepted HEAD: `f845c7aa8accb5eec5873ba81c6057be11358028`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` produced-PDF byte inspection to extract bounded image XObject metadata from page-local and inherited page resources.
- The fake runner now reports per-image page/object provenance, resource names, dimensions, bits-per-component, color-space summaries, filter summaries, interpolation/image-mask flags, soft-mask references, bounded stream byte counts, stream SHA-256 hashes, and stream-skipped flags.
- Added aggregate `pdfImageColorSpaces` and `pdfImageFilters`, plus `fakeRunSequence()` final-output keys `finalPdfImages`, `finalPdfImageColorSpaces`, and `finalPdfImageFilters`.
- Added diagnostics for image counts, stream counts, color-space counts, filter counts, and per-filter counts.
- Updated the WordPress PDF engine handoff example self-test to expose the image metadata in fake-run and final-run summaries.

## Source Truth

- Upstream Pandoc PDF output planning delegates actual rendering to external PDF engines. This slice only ports the bounded native handoff contract needed by the PHP lane: inspect produced PDF bytes returned by the fake runner and expose high-signal resource metadata for review queues.
- This intentionally does not implement TeX, Typst, browser, roff, PDF/A validation, full PDF rendering, image decoding, object stream decoding, xref repair, or external validator behavior.

## Focused Verification

- Baseline before this slice: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 389 assertions, 0 failures`.
- After implementation: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 403 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` passed with `pdf engine handoff self-test ok`.
- PHP lint, JSON validation, and `git diff --check -- lanes/pandoc` were run after the final edits.
- Root harness: not run - isolated micro-slice.

## Mapping Delta

- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` maps one additional PDF-engine support case.
- `lane-status.json` `phpPass` increased from `883` to `884`.
- Focused PDF-engine assertions increased from `389` to `403`.

## Non-Overlap

- This does not overlap the accepted PDF XMP/PDF-A, output-intent, tagged-structure, font-resource, page/outline/annotation/link/embedded-file/encryption/document-info/catalog-language, SyncTeX, TeX recorder, or transcript include-graph slices.
- The new coverage is specifically image XObject metadata from produced PDF bytes and fake-runner diagnostics.

## Dependency Closure

- No new support component is needed. The slice reuses the existing native PHP `PdfEngineHandoff` fake-runner PDF byte inspection path.
- External Pandoc, Cabal, Haskell runners, Word, LibreOffice, zip/unzip, tar, lz4, external template engines, TeX/PDF engines, Typst, browser renderers, roff renderers, external PDF validators, JavaScript, online sanitizers, and online services were not executed.

## Follow-Up

- Keep full image stream decoding, object streams, xref repair, renderer execution, PDF/A conformance validation, and per-engine PDF parity as separate bounded slices.
- A useful next native slice is produced-PDF object-stream/xref recovery preflight or deeper page-resource inheritance edge cases, still without launching renderers or external validators.
