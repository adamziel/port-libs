# docx-openxml-child-node-shape-provenance-20260628

Slice: `plib-spgnp`

## Scope

`DocxOpenXmlReader` now carries metadata-only child-node shape provenance for XML-inspectable DOCX package parts through part inventory rows and `packageProvenance.summary`.

The provenance records direct child node type counts, safe child-type sequences, parent element paths/names/namespaces, and mixed-content parent counts. It does not expose XML text, CDATA text, comment text, processing-instruction values, entity replacement values, or package bytes.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`

Focused test result: `1 test files, 11256 assertions, 0 failures`.

## Metric

- `lane-status.json` `phpPass`: `471 -> 472`
- `lane-status.json` `phpFail`: `0`
