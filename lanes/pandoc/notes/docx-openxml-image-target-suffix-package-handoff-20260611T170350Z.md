# DOCX OpenXML Image Target Suffix Package Handoff

Slice: `plib-tpo55` DOCX OpenXML package ingestion core blocker.
Base: current main `499fb850d`.

## Change

- Normalized internal drawing image relationship targets through the package part path before media/content-type lookup.
- Preserved `targetPart`, `targetQuery`, `targetFragment`, and `targetReferenceSuffix` on image AST nodes for reviewer handoff.
- Added a DOCX fixture regression where `rImage` targets `media/review.png?variant=proof#inline` while the package media bytes remain at `word/media/review.png`.

No Pandoc, Word, LibreOffice, office suites, zip/unzip tools, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 607 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 64209 assertions, 0 failures
