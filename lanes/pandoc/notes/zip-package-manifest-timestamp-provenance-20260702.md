# ZIP Package Manifest Timestamp Provenance

`ZipPackage::packageManifestPreflight()` now carries metadata-only timestamp
provenance for manifest entries. The manifest records DOS, extended timestamp,
and NTFS timestamp source counts, local timestamp counts, source summaries, and
compact provenance rows while preserving the existing `zip-package-manifest-v1`
hash input contract.

Validation for this slice:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageManifestTimestampProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageManifestTimestampProvenanceTest.php`
  passed with 1 file, 49 assertions, and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with 1
  file, 6,290 assertions, and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with 1 file, 4,916 assertions, and 0 failures.
