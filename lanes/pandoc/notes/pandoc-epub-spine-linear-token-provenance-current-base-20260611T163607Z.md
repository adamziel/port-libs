# Pandoc EPUB3 spine linear token provenance

Slice: plib-r503q, 2026-06-11T16:36:07Z
Base: origin/main 446b499acc9f346b3816ee329ba01075bab773fd

This slice keeps EPUB package ingestion native to PHP while preserving OPF
spine `itemref` `linear` token provenance for review packets.

## Coverage

- Invalid `linear` values now remain visible as raw and normalized token
  metadata on spine items.
- Valid mixed-case `yes`/`no` values are normalized for validation while
  preserving the raw source token.
- Package validation reports now include invalid spine-linear diagnostics and
  invalid-item summaries.
- WordPress import package-validation summaries receive the same diagnostics.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 test file, 1190 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 63958 assertions, 0 failures
