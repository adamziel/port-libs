# real-upstream-corpus-btree-index-dynamic-20260531T211243Z-0

## Source Truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/where5.test`
- Ported sections: `where5-1.0` through `where5-4.7`
- Behavior cluster: NULL comparison parity in WHERE predicates and SELECT projections across TEXT, INTEGER, and INTEGER PRIMARY KEY rowsets. Ordinary `<`, `<=`, `=`, `>=`, `>`, and `<>` boundary predicates follow the declared affinity; comparisons against `NULL` return NULL and filter rows; `IS NULL` / `IS NOT NULL` remain two-valued. The INTEGER PRIMARY KEY variants exercise rowid btree semantics.

## Patch

- Added `SQLiteBTreeIndexDynamicCorpusPlan::where5NullComparisonCases()` with 1200 generated real-upstream cases cycling all 50 selected upstream sections.
- Added `SQLiteRealUpstreamBtreeWhere5NullComparisonDynamicTest.php`.
- The focused test uses the PHP `SQLiteSelectSql` executor for actual results and the local SQLite3 extension as an oracle for expected `where5.test` parity.
- Updated `lane-status.json` from `3847998` to `3849202` selected PHP PASS cases (`+1204`).

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere5NullComparisonDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere5NullComparisonDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere5NullComparisonDynamicTest.php`
  - `1 test files, 16383 assertions, 0 failures`

## Dependency Closure

No new support component is needed. This reuses the lane-local B-tree/index dynamic corpus planner, `SQLiteSelectSql` predicate/projection execution, and a local SQLite3 oracle for expected values.

## Non-Overlap

This owns `where5.test` NULL comparison and projection behavior only. It avoids accepted `where2`, `where3`, `where4`, `where6`, `where7`, `where8`, `where9`, `whereA` through `whereN`, `whereB` expression-affinity, `index*`, `indexedby`, `bestindex*`, `without_rowid*`, delete-limit, B-tree page relocation/root-collapse/overflow/freelist/freeblock, JSON, WAL, VFS, PRAGMA, trigger/FK, UPSERT, SELECT expression ORDER BY/GROUP/subquery, and source-neutral cleanup clusters.
