# real-upstream-corpus-btree-index-dynamic-20260531T101026Z-0

Status: ready for integration.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereA.test`
- Upstream sections: `whereA-1.1` through `whereA-6.1`.

Focused coverage:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::whereAReverseUnorderedIndexCases(1000)`.
- Added `SQLiteRealUpstreamBtreeWhereAReverseDynamicTest.php`.
- The generator models upstream reverse-unordered rowid scans, reverse unique-index range scans, explicit `ORDER BY` overriding reverse scan direction, indexed `ORDER BY` avoiding temp sort, dropped-index temp-sort fallback, rowid `IS NULL` impossibility with a unique-index lookup, and OR predicate routing after stat metadata.
- Focused PASS-line movement: `+1003` TestRunner PASS cases with `13566` behavior assertions in the focused whereA file.

Non-overlap:

- This owns `whereA.test` reverse unordered/index scan behavior only.
- It does not repeat accepted `whereB` expression affinity, `whereD` covering OR-index unions, `whereG` planner hints, `whereH/J/K/L/M/N`, `where9`, indexed-by enforcement, expression-index range-cost, B-tree page relocation/root collapse/interior merge/overflow freelist/freeblock release, JSON, WAL, VFS, PRAGMA, trigger/FK, UPSERT, or source-neutral cleanup clusters.
- Mapped denominator coverage remains `1589 / 1589`; this is countable PHP PASS-line growth against already mapped real upstream B-tree/index inventory.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereAReverseDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereAReverseDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereAReverseDynamicTest.php`
  - `1 test files, 13566 assertions, 0 failures`
  - `1003` focused TestRunner PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereAReverseDynamicTest.php`
  - `2 test files, 78734 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r '$p="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Dependency closure:

- No new support component is needed. This reuses the lane-local B-tree/index dynamic corpus planner and existing rowid-scan, unique-index scan, ORDER BY sort-accounting, OR predicate, and stat-metadata modeling.

Root harness:

- Not run - isolated micro-slice.
