# DOCX OpenXML VBA external target rollups

Slice: `plib-drk7q`, DOCX/OpenXML package ingestion.

## Change

`DocxOpenXmlReader` now rolls up external-target kind and scheme counts for VBA
macro package relationships:

- top-level `vbaProject` relationships;
- nested `vbaProjectSignature` relationships;
- nested `wordVbaData` relationships.

The existing safe-vs-unsafe external target policy rows are unchanged. The new
metadata only summarizes already-parsed OPC relationship targets, including
network-path references and unsafe schemes, so consumers can audit macro package
targets from package summary metadata without traversing each nested relationship
row.

Macro project, signature, and data bytes remain blocked from document media
handoff. External targets are not fetched or dereferenced.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed: 1 test file, 9,880 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` was attempted after rebase and
  failed outside this slice: 295 test files, 117,190 assertions, 9,726 failures;
  visible failures include `YamlMetadataReviewTest`.

## Parity Accounting

This extends an existing DOCX OpenXML ingestion test case with additional
metadata assertions; it does not add a new direct-format pass row or change the
lane-status denominator.

No Pandoc binary, Word, LibreOffice, office suite, zip/unzip command, browser
renderer, Node tooling, external validator, online service, live provider test,
or external macro runtime was invoked.
