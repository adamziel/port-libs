## Real Upstream Corpus B-tree/Index Dynamic: bestindex5

Base accepted HEAD: `472430c1daaad1016852e97d68cabd3ea687d289`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindex5.test`
- Sections `bestindex5-1.1` through `bestindex5-2.2.5`

Implemented lane-local coverage:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::bestindex5VirtualTableConstraintCases()`.
- Added `SQLiteRealUpstreamBtreeBestIndex5DynamicTest.php`.
- The dynamic corpus covers virtual-table `xBestIndex` constraint accounting for `!=`, `IS`, `IS NOT`, `IS NULL`, `IS NOT NULL`, commuted constraints, join-derived constraints, row-value constraints, and INTEGER/TEXT affinity-preserving row-value filtering.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeBestIndex5DynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeBestIndex5DynamicTest.php`
- Result: `1 test files, 15467 assertions, 0 failures`
- PASS-line delta: `+1003`

Non-overlap:

- Does not repeat accepted index4/index5 create-index write-order stress, index6 partial-index late regression, index7 partial unique/stat mutation, indexA affinity, bestindex2/bestindex3/bestindex4, JSON, WAL, PRAGMA, trigger, rowvalue, or app-WAL surfaces.
- This slice targets upstream `bestindex5.test`, which was not part of the accepted forty-fourth current-corpus sweep listed in `lane-status.json`.

Dependency closure:

- No new support component needed.
- Reuses lane-local B-tree/index dynamic corpus planner and virtual-table `xBestIndex` constraint accounting.
