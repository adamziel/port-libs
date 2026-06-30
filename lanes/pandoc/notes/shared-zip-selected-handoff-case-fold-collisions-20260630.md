# Shared ZIP Selected Handoff Case-Fold Collisions

Slice: `plib-54n6q`, shared ZIP/OPC package core blocker.

`ZipPackage::entryHandoffPreflight()` now reports selected and readable
case-folded full-name and leaf-name collision summaries before package-reader
byte exposure. The handoff metadata carries folded keys, exact-name variants,
parent directories for leaf collisions, roles, entry names, and byte totals so
DOCX/EPUB/ODT import review can distinguish package parts that collide on
case-insensitive filesystems. Blocked oversized selections remain selected-only
and are excluded from readable collision buckets.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 5849 assertions, 0 failures`

Metric/accounting:

- `lane-status.json` `phpPass`: `472 -> 473`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2316 -> 2317`
- Added `mappedSharedZipSelectedHandoffCaseFoldCollisionCases = 1`

No upstream Pandoc, office suites, `zip`/`unzip`, `ZipArchive`, browser
engines, external validators, or network services were invoked.
