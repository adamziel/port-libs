# ZIP package manifest text encoding summaries

Hook: `plib-psotm`, Pandoc shared ZIP/OPC package core blocker slice.

`ZipPackage::packageManifestPreflight()` now includes deterministic package-level
text encoding rollups for raw entry names and entry comments. The summaries group
UTF-8, CP437, Info-ZIP Unicode path, and Info-ZIP Unicode comment provenance
without changing the per-entry manifest shape.

Each encoding bucket carries entry/file/directory counts, compressed and
uncompressed byte totals, raw text bytes, local/source record bytes,
data-descriptor totals, provenance counters, directory roots, compression
methods, and entry names. The new fields are included in the
`zip-package-manifest-v1` hash payload for stable shared ZIP/OPC handoff review.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 5754 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 5261 assertions, 0 failures`
- `git diff --check -- lanes/pandoc`
- `rg -n "^(<<<<<<<|=======|>>>>>>>)$" lanes/pandoc`

No Pandoc, office suite, TeX/browser/Typst engine, `zip`/`unzip`, ZipArchive,
external validator, Node tooling, Jupyter, online service, or live provider test
was invoked.
