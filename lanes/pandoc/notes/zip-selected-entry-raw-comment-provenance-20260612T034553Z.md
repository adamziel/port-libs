# Pandoc Shared ZIP Selected Entry Raw Comment Provenance

Slice: shared ZIP/OPC package primitive on current base `5fa697be8e`.

## Summary

- Added selected-entry raw comment provenance to
  `ZipPackage::entryHandoffPreflight()` so DOCX, EPUB, ODF, and OPC handoff
  queues can inspect central-directory entry comment metadata while selecting
  package bytes.
- Handoff entries now include decoded comment text, raw comment bytes and hex,
  comment encoding, decoded/raw match state, legacy CP437 comment use,
  Info-ZIP Unicode comment extra use, and raw-comment provenance flags.
- Selected package summaries now expose commented-entry and raw-comment
  provenance buckets plus decoded/raw mismatch, legacy encoding, and Unicode
  comment extra-field counts.
- Added one focused `ZipPackageTest` case covering empty comments, Unicode
  comment extra fields, CP437 raw comments, missing entries, and handoff entry
  propagation.

## Direct-Format Accounting

- `phpPass`: `3186 -> 3187`
- Added cases: `mappedZipSelectedEntryRawCommentProvenanceCases = 1`
- Added assertions: `zipSelectedEntryRawCommentProvenanceAssertions = 122`

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 test file, 3952 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 70274 assertions, 0 failures

No Pandoc, office suites, TeX/browser engines, zip/unzip, Node tooling,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
