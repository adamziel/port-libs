# real-upstream-corpus-btree-index-dynamic-20260531T051557Z-0

Slice: `real-upstream-corpus-btree-index-dynamic-20260531T051557Z-0`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/without_rowid1.test`
- Sections `without_rowid1-5.0` through `without_rowid1-5.7`.

## Ported Behavior

- Added `SQLiteBTreeIndexDynamicCorpusPlan::withoutRowidSecondaryIndexPrimaryKeyTailCases(1000)`.
- Added `SQLiteRealUpstreamWithoutRowidSecondaryIndexTailDynamicTest.php` with 1000 dynamic behavior cases plus source summary, invalid-size guard, and dependency-closure assertions.
- The cluster models SQLite's WITHOUT ROWID secondary-index contract where secondary indexes append trailing primary-key columns so predicates like `b=? AND a>?`, `b=? AND a<?`, `c=? AND a<?`, and `c=? AND a=? AND b>?` can be satisfied from the secondary index scan.

## Non-Overlap

This targets `without_rowid1.test` section 5 secondary-index primary-key-tail behavior. It does not repeat accepted B-tree page relocation, root collapse, overflow freelist/freeblock release, `index7` WITHOUT ROWID partial-index membership, redundant WITHOUT ROWID primary-key de-duplication from `without_rowid6/7`, `index8` ORDER BY/LIMIT scoring, `bestindex*` virtual-table batches, autoindex batches, expression-index/range-cost work, WAL/VFS/JSON/PRAGMA/SELECT clusters, or source-neutral cleanup.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWithoutRowidSecondaryIndexTailDynamicTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWithoutRowidSecondaryIndexTailDynamicTest.php`: `1 test files, 23346 assertions, 0 failures`; 1003 focused PASS lines.
- `git diff --check -- lanes/libsqlite`: clean.

## Dependency Closure

No new support component is needed. This reuses the lane-local B-tree/index dynamic corpus planner for WITHOUT ROWID secondary-index tail key behavior.
