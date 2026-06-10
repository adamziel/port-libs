# Pandoc ODF Compact Manifest Byte-Exposure Provenance

2026-06-11 UTC slice `plib-mbmyt` maps one compact ODT package ingestion case for `OpenDocumentPackage` after refresh onto `origin/main` `d6ebfd9833f108c9c51ffc7913eea8e19776a0ce`.

## Implementation

- Manifest entries now carry package-entry provenance for existence, logical directories, encryption, byte exposure, stored/compressed byte lengths, CRC metadata, declared-size mismatches, and diagnostics.
- Directory manifest declarations such as `Pictures/` remain valid empty-media-type logical entries and do not expose bytes.
- `summarize()` now exposes `manifestReview` counters and item buckets for reviewer handoff before media bytes are exposed.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`: 1 file, 178 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 files, 61767 assertions, 0 failures.

## Accounting

- `phpPass`: 3029 -> 3030.
- mapped denominator: 3169 -> 3170.
- Added `mappedOdfCompactManifestByteExposureCases = 1`.
- Added `odfCompactManifestByteExposureAssertions = 42`.
