# EPUB Nav Item Diagnostics

Slice: `pandoc-epub-nav-item-diagnostics`

## Summary

This slice extends EPUB navigation document diagnostics with malformed list-item
handoff checks. Native PHP review reports now flag:

- `empty-nav-item-label` for `li` entries whose direct `a` or `span` label has no
  visible text.
- `missing-nav-item-href` for anchor labels that do not carry a usable `href`.
- `missing-nav-item-label` for `li` entries without a direct `a` or `span` label.

The diagnostics are exposed in both native EPUB surfaces:

- `EpubPackage` compact `validationReport()` and `navDocumentDiagnostics`
  summaries.
- `EpubReader` rich package import reports and `document` navigation metadata.

No Pandoc, EPUBCheck, browser renderer, `zip`/`unzip`, external validator, online
service, live provider test, or Haskell/Cabal runner is used.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php lanes/pandoc/tests/EpubReaderTest.php`

Focused result: 2 files, 4513 assertions, 0 failures.
Full result after rebase: 42 files, 58613 assertions, 0 failures.

## Accounting

- `lane-status.json` `phpPass`: `2918 -> 2920`
- `lane-status.json` `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3101 -> 3103`
- Added 2 focused EPUB nav item diagnostics cases.
- Focused assertions moved by +43 across `EpubPackageTest.php` and
  `EpubReaderTest.php`.
