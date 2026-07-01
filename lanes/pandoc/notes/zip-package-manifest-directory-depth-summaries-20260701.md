# ZIP Package Manifest Directory Depth Summaries

Slice: `plib-8mqd4`
Date: 2026-07-01

`ZipPackage::packageManifestPreflight()` now emits deterministic `directoryDepthSummaries` alongside the existing per-entry `directoryDepth`, `maxDirectoryDepth`, and `deepestEntryNames` fields.

The summary is metadata-only. It groups package entries by normalized directory depth and records per-depth entry/file/directory counts, compressed and uncompressed byte totals, local/source record byte totals, data-descriptor totals, directory-root counts, package-part extension-key counts, compression-method counts, and sorted entry names. Strict and raw package preflights carry the same summary through shared ZIP package handoff.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` - 1 file, 6,085 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` - 1 file, 5,333 assertions, 0 failures

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node, zip/unzip, validators, or live services were invoked.
