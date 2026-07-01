# ZIP package manifest expansion-ratio buckets

Hook: `plib-sj79k`, Pandoc shared ZIP/OPC package core blocker slice.

`ZipPackage::packageManifestPreflight()` now groups manifest entries into
deterministic expansion-ratio buckets for package handoff review:
zero-byte, up-to-1x, 1x-to-10x, 10x-to-100x, over-100x, and unknown.

Each bucket carries entry/file/directory counts, compressed and uncompressed
byte totals, local/source record byte totals, data-descriptor totals,
directory roots, compression method names, entry names, and largest-ratio
provenance. The bucket summaries are included in the deterministic
`zip-package-manifest-v1` payload alongside the existing size-profile fields.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 5670 assertions, 0 failures`

No Pandoc, office suite, TeX/browser/Typst engine, `zip`/`unzip`, ZipArchive,
external validator, Node tooling, Jupyter, online service, or live provider
test was invoked.
