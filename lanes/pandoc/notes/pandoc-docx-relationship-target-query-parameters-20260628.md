# DOCX Relationship Target Query Parameter Provenance

Slice: `plib-smisf`
Date: 2026-06-28

`DocxOpenXmlReader` now promotes raw relationship target query-parameter provenance into `packageProvenance.summary` for DOCX package review. The summary includes occurrence counts, parameter-name buckets, parameter value buckets, relationship part/source/target rollups, and internal, missing-target, and external relationship counters.

The implementation stays metadata-only: it does not fetch external targets, shell out to Pandoc or office tooling, or expose package part bytes.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file
  - 12731 assertions
  - 0 failures

Lane accounting:

- `phpPass`: 478 -> 479
- `phpFail`: 0
- mapped DOCX relationship target query parameter case: 1
- assertions added in focused DOCX file: 26
