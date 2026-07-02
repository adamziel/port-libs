# ZIP package manifest source-record byte buckets

Hook: `plib-6153j`, Pandoc shared ZIP/OPC package core blocker slice.

`ZipPackage::packageManifestPreflight()` now carries per-entry
`sourceRecordByteBucket` labels and package-level source-record byte bucket
rollups through the shared ZIP package manifest. Buckets are ordered as
`up-to-127-bytes`, `128-to-511-bytes`, `512-to-2047-bytes`, and
`2048-plus-bytes`.

Each bucket records entry/file/directory counts, local/source/central record
byte totals, local header bytes, compressed and uncompressed byte totals, data
descriptor counts, directory roots, package-part extension keys, compression
method names, entry names, and largest source-record entry provenance. The
bucket rollups participate in the deterministic `zip-package-manifest-v1` hash
payload and propagate through strict/raw import package preflights without
exposing package payload bytes.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 6088 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 5333 assertions, 0 failures`

No Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser/Typst engine,
Jupyter, Node tooling, `zip`/`unzip`, external validator, online service, or
live provider test was invoked.
