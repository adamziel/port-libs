# Pandoc ODF OpenDocument Core Current Base

Slice: `pandoc-odf-open-document-core-current-base-20260610T135043Z`

## Summary

Added native ODT package compression provenance to `OdfReader` manifest and
media review reports. Declared ODF manifest entries and media handoff items now
preserve ZIP `compressedByteLength`, `compressionMethod`, and
`compressionMethodName` (`stored`, `deflated`, or `unsupported`) alongside
existing byte length and CRC metadata.

This keeps reviewers able to distinguish stored and deflated ODT media package
parts before exposure without invoking Pandoc, office suites, zip/unzip,
external validators, browser renderers, online services, or live provider tests.
Encrypted media remains inert: readable payload bytes stay withheld while ZIP
container provenance can still be reviewed.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - 1 file, 3505 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 60257 assertions, 0 failures

## Accounting

- `phpPass`: 2975 -> 2976
- `phpFail`: 0
- ODF focused assertions: 3491 -> 3505
- Full Pandoc PHP assertions: 60243 -> 60257

## Boundaries

This slice is limited to ODT package manifest/media compression provenance. It
does not add a writer, does not alter rendered document content, does not expose
encrypted payload bytes, and does not repeat accepted ODT field, chart, object,
manifest size, encryption, RDF, signature, style-diagnostic, or undeclared-entry
coverage.
