# DOCX OpenXML Relationship Type Content-Type Parameters

Slice: `plib-wkp1b`, DOCX OpenXML package ingestion core blocker.

Base: `origin/main` `a886765f4`.

## Scope

`DocxOpenXmlReader` already preserved MIME parameter provenance for content-type
declarations, part inventory, and individual relationship rows. Relationship-type
summary buckets still only exposed the raw content-type strings, so package review
handoff could not see whether a relationship type selected parameterized target
parts without inspecting every relationship row.

## Change

Relationship-type summaries now expose:

- `contentTypeBases`
- `parameterizedContentTypeCount`
- `contentTypeParameterNames`

Each relationship row in the type summary also keeps its target content-type base,
parameterized flag, parameter count, ordered parameter records, and parameter map.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 802 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 65435 assertions, 0 failures`

No Pandoc, Word, LibreOffice, office suite, zip/unzip, browser renderer,
external validator, online service, live provider test, or live-service
provider test was executed.
