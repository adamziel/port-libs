# Pandoc ODF Package Thumbnail Metadata Slice

Date: 2026-06-11 UTC
Base: origin/main b4f5292c8
Bead: plib-pj1r1

## Scope

- `OdfReader` detects `Thumbnails/*` image package parts from both `META-INF/manifest.xml` and undeclared ZIP entries.
- Thumbnail records are exposed through `metadata.odfPackageThumbnails`, the document `packageThumbnails` attribute, and `importReport.packageThumbnails`.
- Package thumbnails remain metadata-only review items and are excluded from document `media` byte handoff.
- Reported thumbnail provenance includes declared, undeclared, missing, encrypted, media-type validity, byte length, CRC, compression, declared size, and review-policy fields.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed: 1 test files, 3812 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 63153 assertions, 0 failures.

## Accounting

- Added one focused `OdfReaderTest` PASS case for declared, missing, and undeclared package thumbnails.
- `phpPass` moves 3063 -> 3064.
- `phpFail` remains 0.

## Boundaries

No Pandoc, office suite, `zip`/`unzip`, browser renderer, external validator, online service, live provider test, or live-service provider test is required for this slice.
