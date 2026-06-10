# Pandoc ODF Compact Manifest Byte-Exposure Provenance

2026-06-11 UTC slice `plib-mbmyt` maps one compact ODT package ingestion case for `OpenDocumentPackage` after refresh onto `origin/main` `48358db4e3163bcc7250e111708e502fb49f8503`.

## Implementation

- Manifest entries now carry package-entry provenance for existence, logical directories, encryption, byte exposure, stored/compressed byte lengths, CRC metadata, declared-size mismatches, and diagnostics.
- Directory manifest declarations such as `Pictures/` remain valid empty-media-type logical entries and do not expose bytes.
- `summarize()` now exposes `manifestReview` counters and item buckets for reviewer handoff before media bytes are exposed.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`: 1 file, 178 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 files, 61759 assertions, 0 failures.

## Accounting

- `phpPass`: 3028 -> 3029.
- mapped denominator: 3168 -> 3169.
- Added `mappedOdfCompactManifestByteExposureCases = 1`.
- Added `odfCompactManifestByteExposureAssertions = 42`.
