# DOCX OpenXML Subdocument External Policy

Slice: `plib-fcis6`

`DocxOpenXmlReader` now carries the shared external-target policy metadata for
`w:subDoc` master-document relationships. Subdocument provenance records
per-item target kind, scheme, allow/deny state, policy issues, unsafe target
lists, and package summary issue codes while keeping subdocument expansion
unsupported and package bytes blocked.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with `1 test files, 9982 assertions, 0 failures`.
