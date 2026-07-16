# Pandoc DOCX Package Root Relationship Suffix Provenance

## Summary

DOCX/OpenXML package ingestion now preserves query and fragment suffix
provenance for package-root relationship role records. `OpcRelationshipGraph`
adds `targetReferenceSuffix`, `targetQuery`, and `targetFragment` to
relationship-role preflight rows, and `DocxReader` carries the same fields into
`metadata.docxPackageRelationships`.

The slice is metadata-only: package loading still resolves and reads the
path-only part names for office-document, core-properties, thumbnail, and
encrypted-package relationships.

## Scope

- `lanes/pandoc/src/OpcRelationshipGraph.php`
- `lanes/pandoc/src/DocxReader.php`
- `lanes/pandoc/tests/DocxReaderTest.php`
- `lanes/pandoc/lane-status.json`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`

No Pandoc, Word, LibreOffice, office suites, `zip`/`unzip`, `ZipArchive`,
browser renderers, external validators, online services, live provider tests, or
live-service provider tests were used.

## Verification

- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - 1 file, 5137 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 88434 assertions, 0 failures

## Accounting

- `phpPass`: 3727 -> 3728
- `phpFail`: 0
- `mapped upstream cases`: 3745 -> 3746
- `mappedDocxPackageRootRelationshipSuffixCases`: 1
- `docxPackageRootRelationshipSuffixAssertions`: 26
