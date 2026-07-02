# DOCX Diagram Sidecar Inventory Role

Bead: `plib-00mio`

This slice adds a generic `diagram-sidecar-part` package inventory role for DOCX/OpenXML diagram relationship targets.

The reader already assigned specific roles such as `diagram-data`, `diagram-layout`, `diagram-quick-style`, and `diagram-colors`. The new aggregate role lets package provenance query all SmartArt diagram sidecar XML parts as one group, matching the existing chart sidecar package-review pattern.

Focused verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
