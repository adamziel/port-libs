# Pandoc ODF Signature Reference Target Provenance

Slice: `plib-6a45a`
Date: 2026-06-11 UTC
Area: Pandoc ODF/ODT OpenDocument package ingestion

## Behavior

`OdfReader` now classifies XML Digital Signature `dsig:Reference` targets and
preserves package-target provenance for reviewer handoff:

- package-part references keep stripped package part paths plus target manifest
  declaration, existence, media type, encryption, compression, byte-length, and
  CRC metadata;
- missing, undeclared, encrypted, external, and unsafe reference targets receive
  explicit inert diagnostics;
- signature metadata exposes aggregate package-part, same-document, external,
  unsafe, missing, undeclared, and encrypted reference counts.

This does not validate signatures, verify digests, trust certificates, decrypt
payloads, expose blocked bytes, or invoke external tooling.

## Accounting

- `phpPass`: `3060 -> 3061`
- `phpFail`: `0`
- `benchmarkDenominator.mapped`: `3191 -> 3192`
- `mappedOdfSignatureReferenceTargetCases`: `1`
- `odfSignatureReferenceTargetAssertions`: `45`

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 3777 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 63082 assertions, 0 failures`
