# Pandoc ODF Compact Manifest Byte-Exposure Provenance

2026-06-11 UTC slice `plib-mbmyt` maps one compact ODT package ingestion case for `OpenDocumentPackage` after refresh onto `origin/main` `de151e220622213e0e3dbebba56b3cde2ec6790d`.

## Implementation

- Manifest entries now carry package-entry provenance for existence, logical directories, encryption, byte exposure, stored/compressed byte lengths, CRC metadata, declared-size mismatches, and diagnostics.
- Directory manifest declarations such as `Pictures/` remain valid empty-media-type logical entries and do not expose bytes.
- `summarize()` now exposes `manifestReview` counters and item buckets for reviewer handoff before media bytes are exposed.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`: 1 file, 178 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 files, 61853 assertions, 0 failures.

## Accounting

- `phpPass`: 3032 -> 3033.
- mapped denominator: 3171 -> 3172.
- Added `mappedOdfCompactManifestByteExposureCases = 1`.
- Added `odfCompactManifestByteExposureAssertions = 42`.
