# Pandoc DOCX Package Relationship Suffix Summary Slice 2026-06-11

Slice: `pandoc-docx-package-relationship-suffix-summary-20260611T1748Z`, based on current main `0f7efc602`.

## Scope

This slice stays inside `lanes/pandoc` and covers DOCX/OpenXML package ingestion. It preserves parsed query and fragment provenance in compact DOCX package relationship summaries so review packets can distinguish missing or external relationship targets by their package part, query string, and fragment instead of only the combined suffix string.

## Implementation

- `DocxOpenXmlReader::packageProvenanceSummary()` now reports aggregate relationship target suffix, query, and fragment counts.
- `relationshipProvenanceSummaryItem()` now carries `targetQuery` and `targetFragment` for compact missing/external target summaries.
- `relationshipInventorySummary()` now preserves query/fragment provenance for external targets while keeping external targets out of package-part/content-type resolution.
- Added a focused DOCX OpenXML test covering root, missing internal, and external relationship suffix provenance.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result: `1 test files, 667 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 64535 assertions, 0 failures`

No Pandoc, office suite, TeX/browser engine, unzip/zip, Jupyter, Node tooling, external validator, online service, live provider test, or live-service provider test was executed.

## Direct-Format Parity Accounting

- Added one focused DOCX OpenXML package ingestion PASS case.
- Lane status `phpPass` moves `3084 -> 3085`; `phpFail` remains `0`.
