# XLSX Defined Name Filter Provenance

Slice: `plib-e1gbt`

This slice adds workbook defined-name metadata to the XLSX reader review payload. It records
defined-name scope, local sheet binding, hidden/function flags, built-in name class, formula
byte/hash provenance, and parsed simple cell/range references without evaluating or exposing the
defined-name formula text.

The added counters cover hidden defined names, print areas, and hidden `_xlnm._FilterDatabase`
names so workbook-level print/filter semantics are visible alongside the existing worksheet and
table auto-filter metadata.

Validation:

- `php -l lanes/pandoc/src/XlsxReader.php`
- `php -l lanes/pandoc/tests/XlsxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XlsxReaderTest.php`
