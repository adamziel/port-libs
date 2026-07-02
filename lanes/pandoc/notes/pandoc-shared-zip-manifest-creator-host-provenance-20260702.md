# Shared ZIP Manifest Creator Host Provenance

Slice: `plib-uhmqn`

`ZipPackage::packageManifestPreflight()` and
`ZipPackage::packagePartProfilePreflight()` now carry metadata-only creator
host/version fields per package entry plus manifest-wide creator-host summary
buckets. The rollups expose known versus unknown creator hosts and
creator-version-below-needed policy buckets before DOCX/EPUB/ODT package readers
consume ZIP payload bytes.

This preserves the `zip-package-manifest-v1` hash contract: the manifest hash
inputs remain limited to stable package identity/order/hash fields, while the
new creator-host summaries stay reviewer metadata.

Accounting:

- `sharedZipManifestCreatorHostSystemCases`: `1`
- `mappedSharedZipManifestCreatorHostSystemCases`: `1`
- `sharedZipManifestCreatorHostSystemAssertions`: `34`
- mapped denominator: `2319 -> 2320`

Verification:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageManifestCreatorHostSystemTest.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageManifestCreatorHostSystemTest.php`
  - `1 file, 34 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 file, 6,290 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 file, 4,916 assertions, 0 failures`

No Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node tooling,
`zip`/`unzip`, external validators, or live services were invoked.
