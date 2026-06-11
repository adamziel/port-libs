# DOCX OpenXML commentsExtended package provenance

Bead: `plib-nfqjy`
Base: current main `f99ec6e057`

## Scope

`DocxOpenXmlReader` now preserves relationship-selected `commentsExtended.xml`
package provenance for compact DOCX ingestion. The slice records the relationship
target, query/fragment suffix, content-type parameters, selected XML root
validation, and `w15:commentEx` resolved/threaded comment metadata, then merges
matching `w15:paraId` data onto imported comment note attrs.

This is metadata-only package ingestion. It does not invoke Pandoc, Word,
LibreOffice, office suites, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 797 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 65386 assertions, 0 failures

## Direct-format parity accounting

- `lane-status.json` `phpPass`: `3097 -> 3098`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3203 -> 3204`
- `mappedDocxOpenXmlCommentsExtendedCases`: `0 -> 1`
- `docxOpenXmlCommentsExtendedAssertions`: `0 -> 38`
