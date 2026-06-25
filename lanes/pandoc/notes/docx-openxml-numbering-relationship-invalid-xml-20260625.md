# DOCX OpenXML numbering relationship invalid XML provenance

Slice: `docx-openxml-numbering-relationship-invalid-xml-20260625`

This slice keeps DOCX numbering relationship ingestion bounded and native. A
relationship-selected numbering part with malformed XML now fails soft: the
reader records the selected XML part provenance and parse issue, leaves the
numbering map empty, and continues importing the rest of the document instead
of aborting before package review metadata is available.

Scope notes:

- No Pandoc, Word, LibreOffice, `zip`, `unzip`, Node tooling, browser engines,
  external validators, or online services are invoked.
- This does not add a new direct-format parity row. It adds one focused DOCX
  OpenXML package-ingestion robustness case.
- Existing numbering relationship target selection and picture-bullet
  relationship metadata are preserved.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed: 1 selected test file, 9455 assertions, 0 failures.
