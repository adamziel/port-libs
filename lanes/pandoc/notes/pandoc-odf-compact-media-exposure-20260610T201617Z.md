# Compact ODF Declared Media Exposure

Bead: `plib-j9np`

## Behavior

- `OpenDocumentPackage` now enriches manifest entries with ZIP package-entry
  provenance: `exists`, `isDirectory`, `byteLength`, `compressedByteLength`,
  `crc32`, and `canExposeBytes`.
- Compact ODT summaries now report declared media presence, missing media
  parts, encrypted media parts, and byte-exposure counts before any review queue
  treats package media as importable.
- Encrypted declared media keeps stored byte/CRC metadata visible for review but
  sets `canExposeBytes` false; missing media is explicit instead of looking like
  an importable attachment.

## Focused Coverage

- Added `reports compact ODT manifest media package exposure and missing parts`
  to `lanes/pandoc/tests/OpenDocumentPackageTest.php`.
- The fixture covers present declared image media, a missing declared image, and
  an encrypted declared image with no byte exposure.

## Accounting

- `phpPass`: `3010 -> 3011`
- `phpFail`: `0`
- Mapped denominator: `3163 -> 3164`
- ODF/OpenDocument mapped cases: `18 -> 19`
- ODF/OpenDocument focused assertions: `414 -> 446`

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 test file, 131 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 61359 assertions, 0 failures

No Pandoc, office suites, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were run.
