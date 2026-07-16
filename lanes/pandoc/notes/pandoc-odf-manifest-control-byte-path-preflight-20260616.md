# ODF Manifest Control-Byte Package Path Preflight

Bead: `plib-h8ve5`
Date: 2026-06-16 UTC
Area: Pandoc ODF/ODT OpenDocument package ingestion

## Scope

This slice tightens native PHP ODT package preflight for manifest package
references. `OpenDocumentPackage` now rejects decoded ASCII control bytes in
`manifest:full-path` package references before ZIP member lookup. Valid encoded
spaces and existing query/fragment suffix handling remain unchanged.

The covered fixtures reject encoded `%1F` and `%7F` path attempts. This is a
bounded package-ingestion guard; it does not alter content parsing or expose any
new package bytes.

## Accounting

- `phpPass`: `16521 -> 16522`
- Upstream manifest mapped cases: `16082 -> 16083`
- Root mapped inventory: `16082 -> 16083`
- ODF/ODT readiness local mapped cases: `87 -> 88`
- ODF/ODT readiness focused assertions: `7060 -> 7062`
- New counters:
  - `mappedOdfManifestControlBytePathCases`: `1`
  - `odfManifestControlBytePathAssertions`: `2`

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `1 test files, 1748 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfOdtShipReadinessStatusTest.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/OpenDocumentReaderTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `5 test files, 7062 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `203 test files, 170718 assertions, 0 failures`

No Pandoc binary, office suite, TeX/browser engine, zip/unzip, ZipArchive, Node
tooling, external validator, online service, or live provider was invoked.
