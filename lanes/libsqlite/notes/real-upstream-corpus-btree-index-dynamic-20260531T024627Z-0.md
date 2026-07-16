# real-upstream-corpus-btree-index-dynamic-20260531T024627Z-0

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index2.test`
- Ported sections: `index2-1.1` through `index2-2.2`

## Behavior

Added a dynamic 1,000-case PHP corpus for the upstream wide-column CREATE
INDEX path:

- 1,000-column table creation.
- 101 wide rows matching the upstream seed plus 100 generated batches.
- 1,000-column `CREATE INDEX t1i1 ON t1(c1,...,c1000)` shape.
- Covering-index ordered lookup with `ORDER BY c1..c6 LIMIT 5`.
- Per-column value preservation from `c1` through `c1000`.

This is non-overlapping with the accepted batch54 B-tree `index5.test`
write-locality behavior. No source-neutral or WordPress-specific API names were
added.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`:
  no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`:
  `1 test files, 349715 assertions, 0 failures`.

## Movement

- Focused PHP PASS-case growth: `+1000`.
- Behavior assertions are inside the existing B-tree/index dynamic corpus test.
- Mapped denominator remains `1589 / 1589`.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP
B-tree/index corpus planning helpers and the existing lane test runner.
