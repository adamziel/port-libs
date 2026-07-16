# Tagged Table Header Graph Current Base

## Scope

- Added review-only tagged table-cell metadata extraction for StructElem direct `/Scope` and `/Headers` keys.
- Added table attribute dictionary extraction from `/A` entries, including `/O /Table` attributes with `/Scope` and `/Headers`.
- Resolved stable compound header graphs through unique StructElem `/ID` values, including direct headers, grouped header-of-header IDs, missing/duplicate/cycle review fields, node summaries, and header titles.
- Propagated the table-cell metadata onto page-level `structure_marked_content` rows so WordPress review metadata can inspect the same MCID-bound graph without promoting table titles into visible text.

## Verification

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php` => 1 test file / 269 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfLayoutPageAnnotationStructTreeTableBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfPageThreadStructTreeAssociatedFileCurrentBaseTest.php lanes/markerpdf/tests/TableBenchmarkBundleBuilderTest.php` => 4 test files / 406 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests` => 1650 test files / 82895 assertions / 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php` => 2 test files / 2585 assertions / 0 failures.

## Accounting

- Expected markerPDF lane movement: `phpPass` 3621 -> 3622; `phpFail` remains 0.
- No GPU/model work was attempted. This remains in the native searchable-PDF, supplied-boundary, no-external-tool markerPDF scope.
