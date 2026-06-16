# ODF/ODT Manifest Namespace Declaration Provenance

Bead: `plib-7j28n`

## Summary

ODF/ODT package ingestion now preserves manifest namespace binding provenance in
the native PHP package path. `OpenDocumentPackage` and `OdfReader` expose root
and file-entry namespace declaration counts, names, records, and maps through
manifest summaries, package inventory items, manifest review entries, and import
report package provenance.

The implementation uses DOM namespace-axis records so `xmlns:*` bindings are
retained even when PHP DOM omits them from the normal attribute map. The implicit
`xml` namespace is excluded from reviewer-facing counts.

## Accounting

- `phpPass`: `16323 -> 16324`
- `phpFail`: `0`
- `mappedOdfManifestNamespaceDeclarationCases`: `1`
- `odfManifestNamespaceDeclarationAssertions`: `34`

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `1 test files, 1668 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfOdtShipReadinessStatusTest.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/OpenDocumentReaderTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `5 test files, 6933 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `195 test files, 169882 assertions, 0 failures`

No Pandoc, office suites, `zip`/`unzip`, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were
invoked.
