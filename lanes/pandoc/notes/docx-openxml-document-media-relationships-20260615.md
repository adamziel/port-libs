# DOCX OpenXML Document Media Relationships

Hook: `plib-v16m4`, Pandoc DOCX OpenXML package ingestion core blocker slice 20260615T111534Z.

## Scope

`DocxOpenXmlReader` now exposes metadata-only package provenance for document image relationships referenced by `a:blip` nodes and unreferenced document image relationship declarations. The report includes target path/query/fragment provenance, internal byte length, CRC32, SHA-256, content-type validation, missing package part diagnostics, external target policy, unsafe external schemes, wrong relationship-type diagnostics, and review byte-exposure policy.

The slice stays native PHP only. It does not invoke Pandoc, Word, LibreOffice, office suites, zip/unzip, ZipArchive, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

## Accounting

- `phpPass`: `3724 -> 3725`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped cases: `3743 -> 3744`
- `mappedDocxOpenXmlDocumentMediaRelationshipCases`: `1`
- `docxOpenXmlDocumentMediaRelationshipAssertions`: `69`

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1` file
  - `2959` assertions
  - `0` failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46` files
  - `88417` assertions
  - `0` failures
