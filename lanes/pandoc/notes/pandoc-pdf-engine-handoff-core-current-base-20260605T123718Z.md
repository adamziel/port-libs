# Pandoc PDF Engine Handoff Core Current Base 20260605T123718Z

Base accepted HEAD: `f0c7b5dddf5f40d8781749792e99d534ff8a1444`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` produced-PDF byte inspection to report bounded cross-reference stream metadata for `/Type /XRef` objects.
- Added bounded object stream preflight metadata for `/Type /ObjStm` objects, including object counts, first-byte offsets, Extends references, unfiltered object-number headers, filters, stream byte counts, stream hashes, and skip reasons.
- Added aggregate xref/object stream filter summaries and `fakeRunSequence()` final-output keys for WordPress review packets.
- Updated the WordPress PDF handoff example self-test to surface the new xref/object stream diagnostics.

## Source Truth

- Upstream Pandoc delegates PDF rendering to external engines. This slice ports the bounded native PHP handoff contract needed after a fake runner supplies produced PDF bytes.
- Modern PDF engines may emit xref streams and object streams. This slice reports those structures for review and diagnostics without decoding compressed object streams, repairing xrefs, or validating full renderer parity.

## Focused Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` failed with `1 test files, 405 assertions, 1 failures` because `pdfXrefStreams` was absent.
- After implementation: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 418 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` passed with `pdf engine handoff self-test ok`.
- PHP lint: `php -l lanes/pandoc/src/PdfEngineHandoff.php`, `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`, and `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` all reported no syntax errors.
- JSON validation: `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` decoded successfully with `JSON_THROW_ON_ERROR`.
- Whitespace check: `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Mapping Delta

- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` maps one additional PDF-engine support case.
- `lane-status.json` `phpPass` increased from `895` to `896`.
- Focused PDF-engine assertions increased from `403` to `418`.

## Non-Overlap

- This does not overlap accepted PDF XMP/PDF-A, output-intent, tagged-structure, font-resource, image XObject, page/outline/annotation/link/embedded-file/encryption/document-info/catalog-language, SyncTeX, TeX recorder, transcript include-graph, or active-action slices.
- The new coverage is specifically produced-PDF `/Type /XRef` stream and `/Type /ObjStm` preflight metadata.

## Dependency Closure

- No new support component is needed. The slice reuses the existing native PHP `PdfEngineHandoff` fake-runner PDF byte inspection path.
- External Pandoc, Cabal, Haskell runners, Word, LibreOffice, zip/unzip, tar, lz4, external template engines, TeX/PDF engines, Typst, browser renderers, roff renderers, JavaScript, external PDF validators, online sanitizers, and online services were not executed.

## Follow-Up

- Keep object-stream decompression, xref-stream entry decoding, xref repair, hybrid-reference reconstruction, renderer execution, PDF/A conformance validation, and per-engine PDF parity as separate bounded slices.
