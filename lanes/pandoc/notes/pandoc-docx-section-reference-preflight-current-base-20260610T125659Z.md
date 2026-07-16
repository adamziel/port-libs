# Pandoc DOCX section-reference package preflight slice

2026-06-10 UTC

Implemented a bounded native PHP DOCX/OpenXML package ingestion hardening slice
for section header/footer references.

## Behavior

- `DocxReader` now records expected relationship and content types for section
  `w:headerReference` and `w:footerReference` targets.
- Header/footer target bodies import only when the relationship type, package
  target, target content type, package existence, and external-target policy are
  clean.
- Wrong relationship types, wrong content types, missing parts, and external
  section targets remain inert reviewer metadata instead of importing a body.

## Validation

- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: 1 test file, 4638 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 44 test files, 60064 assertions, 0 failures.

## Status delta

- `lane-status.json`: `phpPass` 2967 -> 2968, `suiteProgress` 868 -> 869.
- `UPSTREAM_TEST_MANIFEST.json`: mapped denominator 3132 -> 3133.

External tools not run: Pandoc, Cabal/Haskell runners, Word, LibreOffice,
zip/unzip, browser renderers, external validators, online services, live
provider tests, or live-service provider tests.
