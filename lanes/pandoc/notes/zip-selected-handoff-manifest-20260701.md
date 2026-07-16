# ZIP Selected Handoff Manifest

Slice: `plib-uym7e`, shared ZIP/OPC package primitives.

## Summary

- Added `ZipPackage::entryHandoffPreflight()` metadata for a deterministic
  `zip-selected-handoff-manifest-v1` selected-entry handoff manifest.
- The manifest records request order, normalized package names, roles,
  required/optional state, expected kind, readable status, missing/blocked
  status, byte counts, CRC/content hashes, duplicate-request flags, and issue
  buckets without exposing selected entry payload bytes.
- This keeps DOCX/OpenXML, EPUB3, ODF/ODT, and generic OPC package readers on a
  shared native PHP review artifact before package bytes are handed to richer
  importers.

Direct-format parity remains active. This slice does not claim support through
Pandoc, office suites, `zip`/`unzip`, external validators, browser engines, TeX,
or Typst tooling.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 5,122 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 file, 4,781 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 331 files, 122,036 assertions, 9,632 known broad baseline failures
  - `ZipPackageTest.php` passed inside the full run, including the new selected
    handoff manifest case
