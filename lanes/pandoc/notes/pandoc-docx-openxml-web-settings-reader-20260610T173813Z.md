# pandoc-docx-openxml-web-settings-reader-20260610T173813Z

Slice: `plib-pj89`, DOCX OpenXML package ingestion.

This slice extends the lightweight native `DocxOpenXmlReader` package reader to
ingest the WordprocessingML web settings side part. The reader now resolves the
`officeDocument/2006/relationships/webSettings` relationship from
`word/_rels/document.xml.rels`, strips query/fragment when selecting the package
part, records relationship provenance, and exposes parsed `webSettings` metadata
under the document `docx` attribute.

Parsed web settings are bounded metadata only: browser optimization, PNG/VML/CSS
export flags, single-file/folder/long-filename flags, encoding, target screen
size, and pixels-per-inch. This mirrors the existing side-part pattern used by
settings, font table, and theme ingestion without shelling out to Office,
Pandoc, unzip/zip, or external validators.

Focused coverage adds a relationship-targeted `word/web/review-web-settings.xml`
fixture with query/fragment provenance and verifies relationship summary fields
plus parsed web export policy metadata.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 185 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 60925 assertions, 0 failures
