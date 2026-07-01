# DOCX OpenXML Story Content Controls

Date: 2026-07-01

This slice extends native DOCX package ingestion so custom XML data-binding
metadata for `w:sdt` content controls is collected from validated story parts,
not only `word/document.xml`.

Coverage added:

- main document content controls remain covered by the existing summary;
- header and footer content controls now contribute to the same metadata-only
  `contentControls` package review packet;
- valid footnotes, endnotes, comments, and glossary document parts are eligible
  for the same aggregation path;
- each item records `sourceType`, with aggregate story part names and source
  type counts in package provenance.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php lanes/pandoc/tests/DocxOpenXmlPackageInventoryRolesTest.php lanes/pandoc/tests/DocxOpenXmlPackageIdentityTest.php lanes/pandoc/tests/DocxReaderTest.php`
  passed after the final rebase with 4 files, 11938 assertions, 0 failures.

Broad gate note:

- `php tools/run-tests.php lanes/pandoc/tests` was also attempted after an
  earlier rebase
  and failed outside this DOCX slice with 341 files, 126666 assertions, 9267
  failures. Visible failures were in Markdown/plain/native tests such as
  `MarkdownReaderTaskListProfileSurgeTest.php`, `MarkdownReaderTest.php`, and
  `PandocJsonNativeAstTest.php`.
