# real-upstream-corpus-btree-index-dynamic-20260531T232204Z-0

Status: ready for integration.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/where5.test`

Ported sections:

- `where5-1.0` through `where5-3.13`: NULL and non-NULL WHERE comparison results over TEXT, INTEGER, and INTEGER PRIMARY KEY B-tree rows.
- `where5-4.0` through `where5-4.7`: projection truth values for comparison-with-NULL and `IS NULL` / `IS NOT NULL` expressions over rowid-backed rows.

Focused addition:

- `SQLiteBTreeIndexDynamicCorpusPlan::where5NullComparisonCases(1200)` now records expected ordered result rows, projection values, matched row counts, and NULL-result counts.
- `SQLiteRealUpstreamBtreeWhere5NullComparisonDynamicTest.php` adds 1203 focused TestRunner PASS cases from the real upstream `where5.test` script.

Non-overlap:

- This owns only upstream `where5.test` NULL comparison behavior.
- It avoids accepted `where2`, `where4`, `where6`, `where7`, `where8`, `where9`, `whereA`, `whereC`, `whereD`, `whereE`, `whereF`, `whereG`, `whereH`, `whereI`, `whereJ`, `whereK`, `whereL/M/N`, `index*`, `bestindex*`, B-tree page relocation/root-collapse/interior merge/overflow/freelist release, JSON, WAL, VFS, PRAGMA, SELECT, trigger, UPSERT, and source-neutral cleanup clusters.

Dependency closure:

- No new support component is needed. This reuses lane-local B-tree/index dynamic corpus helpers and `SQLiteAffinityComparison` for affinity coercion, NULL comparison truth values, rowid B-tree scan ordering, and projection NULL-result handling.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere5NullComparisonDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere5NullComparisonDynamicTest.php` passed with `1 test files, 25479 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` passed with `1 test files, 384926 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed with `1 test files, 3 assertions, 0 failures`.
