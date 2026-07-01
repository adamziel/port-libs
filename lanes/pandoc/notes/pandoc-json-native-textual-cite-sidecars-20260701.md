# Pandoc JSON/native textual Cite sidecars

## Scope

`plib-d9pse` closes one bounded JSON/native constructor completeness gap in textual NativeReader input. Textual `Cite` constructors now attach the same reusable sidecars as JSON/native cite input:

- `citationNative` on parsed citation records.
- `citationPrefixNative` and `citationSuffixNative` on parsed affix inline lists.
- `citationRecordsNative`, `constructor = Cite`, and `native` on single citations and citation groups.

This keeps unchanged textual native citation records available for `PandocJsonWriter` and `NativeWriter` JSON/native handoff while still allowing edited citation fields to regenerate canonical records.

## Validation

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTextualCitationConstructorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTextualCitationConstructorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php lanes/pandoc/tests/PandocJsonNativeAstTest.php lanes/pandoc/tests/NativeReaderTextualCitationConstructorTest.php`

The focused textual citation sidecar test passed with 32 assertions. The broader selected NativeReader plus Pandoc JSON/native files passed with 3 files, 6,788 assertions, and 0 failures.

`NativeReaderEscapeTest.php` plus `PandocJsonNativeRawHtmlAdjacencyBoundaryTest.php` remains baseline-red on this target with 3 raw-format sidecar expectation failures outside this citation slice.
