# Shared ZIP Package Manifest Data Descriptor Review

Hook: `plib-7kuzz`, Pandoc shared ZIP/OPC package core blocker slice.

## Summary

- Added package-wide data descriptor review metadata to `ZipPackage::packageManifestPreflight()`.
- The manifest now reports signed/unsigned descriptor counts, ZIP64-sized descriptor counts, zero local-header placeholder counts, value/central-directory match counts, descriptor issue buckets, and descriptor provenance entries.
- Per-entry package manifest rows now carry bounded descriptor provenance alongside existing source-byte-span hashes without changing the manifest hash payload.

## Accounting

- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2319 -> 2320`
- New mapped case: `mappedSharedZipPackageManifestDataDescriptorReviewCases`
- Focused assertions: `sharedZipPackageManifestDataDescriptorReviewAssertions = 53`

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageManifestDataDescriptorReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageManifestDataDescriptorReviewTest.php`
  - Result: 1 file, 53 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageManifestDataDescriptorReviewTest.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: 2 files, 6,343 assertions, 0 failures.

No Pandoc, office suites, `zip`/`unzip`, `ZipArchive`, browser tooling, live services, or external validators were invoked.
