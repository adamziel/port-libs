# DOCX OpenXML Relationship XML Preflight

Slice: `plib-crnlj`, DOCX OpenXML package ingestion core blocker.

Base: `origin/main` `0c646c4736ec5f7580aafa48e454889c9c0b1084`.

## Scope

`DocxOpenXmlReader` already indexed root, document, and secondary `.rels`
sidecars for package review, but the compact DOCX package provenance did not
surface record-level relationship XML diagnostics. Duplicate relationship IDs
or missing required attributes could be hidden by the tolerant `id => record`
map used during ingestion.

## Change

DOCX package provenance now reports relationship XML preflight metadata for
each relationship part:

- relationship record and invalid-record counts;
- duplicate relationship ID groups;
- relationship XML issue counts;
- issue records with ordinal, visible `Id`/`Type`/`Target`/`TargetMode`,
  duplicate-origin ordinal, and issue codes.

The package summary also aggregates invalid relationship part counts,
relationship XML issue counts, affected `.rels` parts, and issue records.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 508 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 63849 assertions, 0 failures`

No Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were run.
