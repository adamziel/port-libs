# real-upstream-corpus-btree-index-dynamic-20260531T145904Z-0

Status: ready for integration.

This slice adds a non-overlapping real upstream B-tree/index dynamic corpus batch from the hydrated SQLite checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/where4.test`.
- Upstream sections covered: selected `where4-1.1` through `where4-8.2`.
- Focus: indexed `IS NULL` and bound-NULL constraints, unary-plus residual guards, composite index range/equality probes, index-ordered NULL/BLOB/TEXT rows, LEFT JOIN null-extension preservation, composite `IN` probes with NULL list elements, correlated indexed subquery `c<NULL` behavior, and UNIQUE-index NULL lookup rows.
- PHP behavior added: `SQLiteBTreeIndexDynamicCorpusPlan::where4IsNullIndexOptimizationCases(1000)` plus `SQLiteRealUpstreamBtreeWhere4IsNullDynamicTest.php`.
- Focused PASS-line growth: `1003` distinct TestRunner PASS cases with `19810` behavior assertions.

Non-overlap:

- This owns upstream `where4.test` selected IS NULL/index behavior only.
- It does not repeat accepted `where7`, `where8`, `where9`, `whereA`, `whereC`, `whereD`, `whereE`, `whereF`, `whereG`, `whereH`, `whereI`, `whereJ`, `whereK`, `whereL/M/N`, `index*`, `indexedby`, `bestindex*`, B-tree page relocation/root collapse/interior merge/overflow freelist/freeblock release, JSON, WAL, VFS, PRAGMA, SELECT, trigger, UPSERT, or source-neutral cleanup clusters.
- Count type: selected PHP PASS-line growth only. Mapped upstream denominator remains `1589 / 1589`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere4IsNullDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere4IsNullDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere4IsNullDynamicTest.php`
  - `1 test files, 19810 assertions, 0 failures`
  - `1003` focused PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere4IsNullDynamicTest.php`
  - `2 test files, 84978 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r '$p="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - Passed with no output.

Dependency closure: no new support component is needed. This reuses the lane-local B-tree/index dynamic corpus planner and existing IS NULL optimization, NULL-aware `IN`, composite-key probe, LEFT JOIN null-extension, and UNIQUE NULL lookup modeling helpers.

Root harness: not run - isolated micro-slice.
