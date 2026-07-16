# DOCX OpenXML VBA external target policy

Slice: `plib-drk7q`, DOCX/OpenXML package ingestion.
Base: current `origin/main`.

## Change

`DocxOpenXmlReader` now preserves external-target policy metadata for DOCX VBA
macro package relationships:

- top-level `vbaProject` relationships;
- nested `vbaProjectSignature` relationships;
- nested `wordVbaData` relationships.

The review packets now distinguish allowed external macro targets from unsafe
schemes, expose unsafe target lists and issue-code buckets, and carry the same
relationship-type rollups already available to generic package provenance.
Macro project, signature, and data bytes remain blocked and no external target
is fetched.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed: 1 test file, 9,565 assertions, 0 failures.

## Movement

- `lanes/pandoc/lane-status.json` `phpPass`: `439 -> 440`.
- Added one focused `DocxOpenXmlReaderTest` case for safe and unsafe external
  VBA project, signature, and `wordVbaData` targets.

## Non-Overlap

This does not repeat the previous ActiveX binary external-target policy slice.
It is limited to VBA macro package relationship metadata and reuses the generic
OPC relationship external-target policy without adding conversion, validation,
network fetching, or byte exposure.
