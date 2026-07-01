# Pandoc ODF Reader ZIP Timestamp Provenance

Bead: `plib-hm1yk`

## Summary

`OdfReader` now carries ZIP modification-time provenance from native ODT
packages into rich package review metadata. The reader exposes timestamp source,
central/local timestamp values, DOS validity, issue lists, aggregate timestamp
counters, and invalid-DOS timestamp rows through `packageProvenance`, document
manifest metadata, and metadata-only package identity.

This aligns the rich ODF reader handoff with the existing compact
`OpenDocumentPackage` timestamp provenance. Package bytes and byte-exposure
policies are unchanged.

## Accounting

- ODF/ODT local mapped cases: `90 -> 91`
- ODF/ODT focused assertions: `1546 -> 1577`
- Focused PHP behavior tests: `469 -> 470`

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderZipTimestampProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderZipTimestampProvenanceTest.php`
  - `1 test files, 31 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfReaderZipTimestampProvenanceTest.php`
  - `2 test files, 83 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderStylePackageProvenanceTest.php lanes/pandoc/tests/OdfReaderZipTimestampProvenanceTest.php`
  - `2 test files, 74 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderZipTimestampProvenanceTest.php`
  - `2 test files, 1927 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 5066 assertions, 0 failures`

No Pandoc, office suites, `zip`/`unzip`, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were
invoked.
