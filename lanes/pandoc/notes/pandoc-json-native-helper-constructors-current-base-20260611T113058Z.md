# Pandoc JSON/native helper constructors current base 20260611T113058Z

Bead: `plib-mn0q8`
Base: `ffd6e31e5578577d820a4d341abc9c46c84490d9`

## Scope

`PandocJsonReader` and `NativeReader` now retain Pandoc helper constructor names while preserving normalized shared AST fields.

Covered helper provenance:
- ordered-list `listStyleConstructor` and `listDelimiterConstructor`
- inline `quoteTypeConstructor` and `mathTypeConstructor`
- table `alignmentConstructors` and `columnWidthConstructors`
- table-body `rowHeadColumnsConstructor`
- table-cell `alignmentConstructor`, `rowSpanConstructor`, and `colSpanConstructor`

This keeps reviewer packets constructor-complete for JSON/native AST handoff without changing writer output or invoking external Pandoc tooling.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 570 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  - `1 test files, 299 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 62830 assertions, 0 failures`
