# Pandoc ODF Compact Manifest Byte-Exposure Provenance

2026-06-10 UTC slice `plib-mbmyt` maps one compact ODT package ingestion case for `OpenDocumentPackage` after refresh onto `origin/main` `e7815c51c317703f8adf488b8c2647a84f776370`.

## Implementation

- Manifest entries now carry package-entry provenance for existence, logical directories, encryption, byte exposure, stored/compressed byte lengths, CRC metadata, declared-size mismatches, and diagnostics.
- Directory manifest declarations such as `Pictures/` remain valid empty-media-type logical entries and do not expose bytes.
- `summarize()` now exposes `manifestReview` counters and item buckets for reviewer handoff before media bytes are exposed.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`: 1 file, 178 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 files, 61724 assertions, 0 failures.

## Accounting

- `phpPass`: 3026 -> 3027.
- mapped denominator: 3168 -> 3169.
- Added `mappedOdfCompactManifestByteExposureCases = 1`.
- Added `odfCompactManifestByteExposureAssertions = 42`.
