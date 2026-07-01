# ZIP package manifest name-length buckets

Hook: `plib-jty4i`, Pandoc shared ZIP/OPC package core blocker slice.

`ZipPackage::packageManifestPreflight()` now carries per-entry entry-name
byte counts and deterministic name-length bucket labels through shared ZIP
package preflights. The package manifest also exposes ordered bucket rollups
for up-to-15, 16-to-63, 64-to-127, and 128-plus byte entry names.

Each bucket records entry/file/directory counts, entry-name/local-name/central
name bytes, compressed and uncompressed byte totals, local/source record byte
totals, directory roots, package-part extension keys, entry names, and longest
entry-name provenance. The same summaries are part of the deterministic
`zip-package-manifest-v1` hash payload and the strict/raw import preflight
handoffs.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 6033 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `2 test files, 11366 assertions, 0 failures`

No Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser/Typst engine,
Jupyter, Node tooling, `zip`/`unzip`, external validator, online service, or
live provider test was invoked.
