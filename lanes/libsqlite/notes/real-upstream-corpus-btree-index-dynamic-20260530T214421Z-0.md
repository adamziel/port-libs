# Real Upstream Corpus B-tree/Index Dynamic Slice

Session: `port-dev-sqlite-yield-dyn-real-btree-20260530T214421Z`
Base accepted HEAD: `551608c47b9b5c9b4c74afdd6349b99f03720fcd`

## Upstream Sources

- `test/index8.test` sections `1.0`, `1.0eqp`, `1.1`, `1.1eqp`: ORDER BY/LIMIT planner choice when an index covers the WHERE column and avoids table lookup.
- `test/index7.test` sections `index7-2.1` through `index7-2.104`: WITHOUT ROWID partial-index membership before and after key/payload mutation.
- `test/indexA.test` sections `1.1` through `1.7` and `4.1` through `8.1`: partial-index equality, RIGHT/LEFT JOIN routing, collation guards, bloom-filtered joins, INDEXED BY, and constant expression indexes.
- `test/indexfault.test` sections `1.1`, `2.1`, `2.2`, `3.1`, and `3.3`: CREATE INDEX sorter malloc/I/O/temp-open/temp-write fault retry and rollback behavior.

## Focused Delta

- Added `+3719` focused TestRunner PASS cases to `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`.
- Focused command passed: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`
- Result: `1 test files, 225812 assertions, 0 failures`, `14632` PASS lines.
- Expected `phpPass` movement if accepted: `782971 -> 786690`.
- Mapped denominator remains `1589 / 1589`; this is PASS-line growth, not mapped-denominator growth.

## Non-overlap

This slice does not repeat the already accepted B-tree `index4` coverage, B-tree overflow/freeblock/freelist/page-move/root-collapse clusters, JSON table cursor/source/constraint clusters, WAL/VFS apply clusters, or SQL expression ORDER/GROUP/subquery clusters. It extends an existing B-tree/index real upstream dynamic corpus with unused source-backed generators already present in the accepted lane source.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP corpus generators and the focused TestRunner. Full SQLite release/all runner parity remains unclaimed.
