# DOCX OpenXML Bibliography External Policy

Slice: `plib-vsnib`

`DocxOpenXmlReader` now carries shared external-target policy metadata for
DOCX bibliography package relationships. Bibliography provenance records
per-item target kind, scheme, allow/deny state, policy issues, unsafe target
lists, and package summary issue codes without fetching external bibliography
targets or exposing bibliography package bytes.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with `1 test files, 9983 assertions, 0 failures`.
