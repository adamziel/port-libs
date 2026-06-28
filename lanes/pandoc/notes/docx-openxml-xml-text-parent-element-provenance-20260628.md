# DOCX OpenXML XML Text Parent Element Provenance

Bead: `plib-foq87`

This bounded DOCX/OpenXML package-ingestion slice extends metadata-only XML text-node provenance for XML-inspectable package parts. `DocxOpenXmlReader` now reports each text node's parent namespace, local name, and qualified name, plus per-part and package-level parent namespace/local-name/qualified-name buckets through `packageProvenance.summary`.

Raw XML text remains blocked from package review metadata: only parent element names, namespaces, byte lengths, whitespace flags, CRC32, and SHA-256 digests are exposed. The slice does not invoke Pandoc, office suites, `zip`/`unzip`, browser/XML validators, or external services.

Validation:
- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed: 1 test file, 10,866 assertions, 0 failures.

Direct-format accounting: no new format token is claimed. The focused DOCX/OpenXML test matrix gains one mapped PASS case for text-node parent element provenance while phpFail remains 0 in the focused gate.
