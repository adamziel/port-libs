# VDBE Sorter DISTINCT Collation Current Source Next116

## Behavior

- Added `SQLiteVdbeSorterDistinctSourceTransitionPlan` to compare VDBE-style DISTINCT sorter tapes across current/next row sources.
- The planner reuses `SQLiteVdbeSortCompare` so duplicate classes honor SQLite affinity, NULL ordering, and `BINARY`/`NOCASE`/`RTRIM` collation equality.
- It reports retained, inserted, deleted, moved, and changed representative rows plus duplicate rows skipped behind each DISTINCT class.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeSorterDistinctCollationCurrentSourceNext116Test.php`
- `php lanes/libsqlite/examples/application-vdbe-sorter-distinct-collation-current-source-next116.php --self-test`
- `php -l lanes/libsqlite/src/SQLiteVdbeSorterDistinctSourceTransitionPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVdbeSorterDistinctCollationCurrentSourceNext116Test.php`
- `php -l lanes/libsqlite/examples/application-vdbe-sorter-distinct-collation-current-source-next116.php`
- `git diff --check -- lanes/libsqlite`

## Non-Overlap

This avoids accepted batch107/108 VDBE affinity/collation sorter source ordering, accepted next106 DISTINCT refresh/reseek behavior, aggregate DISTINCT cursor basics, JSON table constraints, B-tree freelist/overflow, WAL/pager/VFS durability, and encoding numeric-affinity clusters. The narrower surface is current-vs-next transition accounting for collation-defined DISTINCT sorter classes after a copied `wp_options` source changes.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP VDBE sorter comparison, affinity coercion, and collation semantics.
