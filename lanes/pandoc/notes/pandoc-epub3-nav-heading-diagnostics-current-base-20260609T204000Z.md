# EPUB Nav Heading Diagnostics

Slice: `pandoc-epub-nav-heading-diagnostics`

## Summary

This slice extends the existing EPUB navigation document diagnostics with a bounded
missing-heading report for primary `toc`, `landmarks`, and `page-list` nav
sections. A primary nav section can now have valid ordered-list entries while
still surfacing `missing-primary-nav-section-heading` when no direct heading label
is present for WordPress review handoff.

The behavior is implemented in both native PHP EPUB surfaces:

- `EpubReader` rich package import reports.
- `EpubPackage` compact package validation summaries.

No Pandoc, EPUBCheck, browser renderer, `zip`/`unzip`, external validator, online
service, live provider test, or Haskell/Cabal runner is used.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`

Focused result: 2 files, 4387 assertions, 0 failures.
Full result after rebase: 42 files, 57192 assertions, 0 failures.

## Accounting

- `lane-status.json` `phpPass`: `2837 -> 2839`
- `lane-status.json` `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3052 -> 3054`
- `mappedEpubNavDocumentDiagnosticsCases`: `1 -> 3`
- `epubNavDocumentDiagnosticsAssertions`: `22 -> 51`
