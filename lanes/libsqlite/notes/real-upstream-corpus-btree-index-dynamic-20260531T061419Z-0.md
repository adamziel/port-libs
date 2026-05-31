# real-upstream-corpus-btree-index-dynamic-20260531T061419Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereC.test`
- Sections `whereC-1.1` through `whereC-1.15`.

## Ported Behavior

- Composite `i1(a,b)` index equality with rowid range constraints.
- String-literal rowid equality and `IS` coercion.
- Empty rowid ranges for reversed `BETWEEN` and NULL boundaries.
- `ORDER BY i ASC` and `ORDER BY i DESC` result direction preservation.

## Local Files

- `lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereCRowidCompositeDynamicTest.php`

## Non-Overlap

This owns upstream `whereC.test` rowid/composite-index range behavior. It avoids accepted `whereE`, `whereI`, `whereJ`, `whereK`, `whereL/M/N`, `where9`, `bestindex*`, `index5` write locality, `index8` order/limit, B-tree page move/root collapse/overflow freelist/freeblock, JSON, WAL, VFS, PRAGMA, SELECT expression ORDER BY, and source-neutral cleanup clusters.

## Dependency Closure

No new support component is needed; this reuses lane-local B-tree/index dynamic corpus helpers for rowid range, composite index equality, literal coercion, empty range, and ORDER BY direction behavior.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereCRowidCompositeDynamicTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereCRowidCompositeDynamicTest.php`: `1 test files, 13871 assertions, 0 failures`, with `1003` PASS lines.
- `git diff --check -- lanes/libsqlite`: passed with no output.
