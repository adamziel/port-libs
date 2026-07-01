# DOCX OpenXML Chart External Policy

Slice: `plib-gpsvm`

`DocxOpenXmlReader` now carries shared external-target policy metadata for
DOCX chart package relationships. Chart provenance records per-item target
kind, scheme, allow/deny state, policy issues, unsafe target lists, and package
summary issue codes without fetching external chart targets or exposing chart
package bytes.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with `1 test files, 9983 assertions, 0 failures`.
