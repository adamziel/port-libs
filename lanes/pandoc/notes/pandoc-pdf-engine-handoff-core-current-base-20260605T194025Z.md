# Pandoc PDF Engine Handoff Core Current Base - Article Threads

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T194025Z`

Base accepted HEAD: `e5f4ae22bc489e70872199901587563b2b7641a4`

## Scope

- Added bounded native produced-PDF article thread extraction to `PdfEngineHandoff`.
- Catalog `/Threads` entries may be arrays, referenced arrays, referenced thread dictionaries, or inline thread dictionaries.
- Thread summaries now expose `object`, `infoTitle`, `infoAuthor`, `infoSubject`, `firstBead`, `beadCount`, and bead `object`/`pageObject`/`rect`/`next`/`prev` metadata.
- Bead traversal is capped at 64 entries and stops on cycles back to the first bead.
- Fake-runner diagnostics now include `pdf-byte-threads`, `pdf-byte-thread-beads`, and `pdf-byte-thread-info-titles`.
- `fakeRunSequence()` now carries the metadata as `finalPdfThreads`.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 500 assertions, 0 failures`
- Syntax:
  - `php -l lanes/pandoc/src/PdfEngineHandoff.php`
  - `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
  - Result: no syntax errors
- Focused test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 507 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  - Result: `pdf engine handoff self-test ok`

## Status Delta

- `phpPass`: `1051 -> 1052`
- Manifest mapped checks: `1504 -> 1505`
- `pdfEngineHandoffCoreCases`: `10 -> 11`
- `mappedPdfEngineHandoffCoreCases`: `10 -> 11`
- `pdfEngineHandoffCoreAssertions`: `95 -> 102`

## Dependency Closure

No new support component was needed. This slice reuses native PHP PDF object scanning, dictionary parsing, indirect-reference resolution, and fake-runner handoff reporting.

Full upstream Pandoc runner parity remains gated on a hydrated Pandoc checkout and Cabal runner dependency plan for `test-pandoc` and `test-pandoc-lua-engine`. No Pandoc, Cabal solver/build/test command, Haskell runner, TeX/PDF engine, Typst, browser renderer, roff, external PDF validator, Word, LibreOffice, zip/unzip, external converter, online service, or live provider was executed.

## Follow-Up

Keep widget appearance streams, annotation/action cross-links, article-thread semantic order validation, XFA packet dereferencing/decompression, signature byte-range validation, PDF/UA structure validation, real renderer execution, and external PDF validator parity as separate bounded slices.
