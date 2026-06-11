# Pandoc Shared ZIP EOCD Fixed Field Provenance

Bead: `plib-d1mw5`
Date: 2026-06-11 UTC
Area: Pandoc shared ZIP/OPC package primitives

## Behavior

`ZipPackage` now exposes fixed-field byte provenance for the ZIP
end-of-central-directory record before package construction.

`ZipPackage::endOfCentralDirectoryFixedFieldsPreflight()` reports:

- EOCD fixed-header offsets and field values for signature, disk numbers,
  entry counts, central-directory size/offset, and package-comment length;
- package-comment, declared archive end, trailing-byte, and truncated-comment
  boundary metadata;
- split and ZIP64 sentinel issues without exposing package part bytes.

The packet is carried through both `rawStrictImportPreflight()` and object
`strictImportPreflight()`, matching the existing central/local fixed-header
review surfaces. No Pandoc, office suites, `zip`/`unzip`, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

## Accounting

- `phpPass` note ledger: `3134 -> 3135`
- `phpFail`: `0`
- `benchmarkDenominator.mapped`: `3218 -> 3219`
- `mappedZipEocdFixedFieldCases`: `+1`

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 3575 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 66793 assertions, 0 failures`
