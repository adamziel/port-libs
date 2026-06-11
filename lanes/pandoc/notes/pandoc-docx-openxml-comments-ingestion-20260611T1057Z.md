# Pandoc DOCX OpenXML Comments Ingestion

Implemented one bounded native PHP DOCX/OpenXML package-ingestion slice for
relationship-selected Word comments.

## Behavior

- `DocxOpenXmlReader` now resolves document-level `comments` and
  `commentsExtended` relationships, including nonconventional relationship
  target paths with query or fragment suffixes.
- `comments.xml` entries are exposed in `docx.comments` summaries and
  `w:commentReference` inline nodes become shared `note` AST nodes with
  `sourceType=comment`.
- `commentsExtended.xml` entries expose paragraph IDs, resolved state, and
  thread parent IDs, and matching comment notes receive the same inert review
  metadata.

This slice does not invoke Pandoc, Word, LibreOffice, zip/unzip, browser
renderers, external validators, online services, or live provider tests.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result: 1 test file, 353 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 44 test files, 62507 assertions, 0 failures.

Status delta on required base `77ddea8948834d046d1506811ffd5da5c6f4f6cd`:
`phpPass` moves from `3049` to `3050`; focused checks move from `948` to
`949`.
