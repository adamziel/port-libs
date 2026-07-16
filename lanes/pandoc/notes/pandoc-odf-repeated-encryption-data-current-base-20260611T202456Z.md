# ODF Repeated Encryption Data Records

## Scope

Implemented one bounded ODF/ODT package-ingestion slice in native PHP:

- `OdfReader` now preserves repeated sibling `manifest:encryption-data` records on a single `manifest:file-entry`.
- `OpenDocumentPackage` now mirrors the same metadata-only record provenance in compact package review surfaces.
- The first encryption record remains the compatibility view (`checksumType`, `checksum`, `algorithm`, etc.).
- Full sibling records are preserved as `records` with `recordCount`, and repeated records add `odf-manifest-encryption-multiple-encryption-data`.
- Encrypted package bytes remain blocked from media exposure.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - 1 test file, 4018 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 test file, 382 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 65926 assertions, 0 failures

## Accounting

- Added one focused ODF package-ingestion PASS case.
- `phpPass`: 3116 -> 3117
- `phpFail`: 0
- `mappedOdfManifestEncryptionDataRecordCases`: 1
- `odfManifestEncryptionDataRecordAssertions`: 35

## Non-Overlap

This does not repeat the accepted ODF manifest encryption child multiplicity
slice for repeated algorithms, key derivations, start-key generations, or
unknown extension children inside one encryption record. This slice covers
repeated sibling `manifest:encryption-data` records on the manifest entry
itself.
