# DOCX OpenXML Selected Relationship Provenance

Slice: `plib-z08fn`, DOCX OpenXML package ingestion core blocker.

Base: `origin/main` `c51492fa8d9fa7bad27875d056930b8b3db9acc0`.

## Scope

`DocxOpenXmlReader` already preserved target query/fragment suffixes and content-type
resolution in package-wide relationship inventories, but direct selected part summaries
such as `settingsRelationship` and `fontTableRelationship` only exposed target paths
and content types. That left reviewer packets without equivalent provenance for the
relationship that actually selected a loaded DOCX package part.

## Change

Selected relationship summaries now include:

- `targetQuery`
- `targetFragment`
- `targetReferenceSuffix`
- `contentTypeSource`
- `defaultExtension`
- `overridePartName`

The existing `contentType` value now comes from the same content-type resolution helper
used by package relationship inventories, keeping override/default/missing provenance
consistent.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 476 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 63777 assertions, 0 failures`

No Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers, external
validators, online services, live provider tests, or live-service provider tests were
run.
