# DOCX OpenXML note inventory roles

Slice: `plib-akdu4`, DOCX/OpenXML package ingestion.

This slice adds semantic package inventory roles for relationship-selected note and
review sidecar parts in `DocxOpenXmlReader`:

- `footnotes`
- `endnotes`
- `comments`
- `comments-extended`

The roles are added in addition to the existing `document-relationship-target`
role so WordPress/package review queues can distinguish note package sidecars in
`packageProvenance.parts[*].roles` and aggregate `summary.roleCounts` without
using those sidecars as document media.

Focused coverage adds one `DocxOpenXmlReaderTest` case for a bounded synthetic
DOCX package with footnotes, endnotes, comments, and commentsExtended
relationships. The focused test verifies both per-part role lists and aggregate
role counts.

Accounting:

- `phpPass`: 3274 -> 3275
- `phpFail`: 0
- `mappedDocxOpenXmlNoteInventoryRoleCases`: 1
- `docxOpenXmlNoteInventoryRoleAssertions`: 8

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 2238 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 73424 assertions, 0 failures

No Pandoc, Word, LibreOffice, office suites, zip/unzip, ZipArchive, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests were invoked.
