# ZIP package version-made-by summaries

`ZipPackage::centralDirectoryFixedHeaderPreflight()` and `ZipPackage::packageManifestPreflight()` now expose exact central-directory `versionMadeBy` buckets for shared ZIP/OPC package handoff.

Each summary carries the raw value and hex form, creator host system id/name, creator version, known-host flag, entry/file/directory counts, compressed and uncompressed byte totals, compression method names, and entry names. Manifest-level summaries also include local-record and source-record byte totals.

The raw strict-import path carries these summaries through `centralDirectoryFixedHeaders` and `packageManifest` metadata without reading or exposing entry payload bytes.

Validation for `plib-jzrj8`: `php -l` on `ZipPackage.php` and `ZipPackageTest.php`; `jq empty` on `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`; `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` with 1 file, 6048 assertions, 0 failures; `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` with 1 file, 5333 assertions, 0 failures; diff whitespace and conflict-marker checks. No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node, zip/unzip, validators, or live services were invoked.
