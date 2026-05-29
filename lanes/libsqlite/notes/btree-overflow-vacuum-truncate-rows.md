# SQLite B-tree Overflow Vacuum Truncate Rows

## Behavior

This consolidation replaces the former numbered truncate helper with the stable
`SQLiteOverflowVacuumTruncatePlan::overflowVacuumTruncateRows()`, a summary for
incremental-vacuum tail truncation where an auto-vacuum pointer-map page is
removed between released overflow tail pages.

The related freeblock truncate rows summarize released overflow pages only. This
summary records the omitted pointer-map page itself as
`auto-vacuum-pointer-map-page`, with no delete source, no freelist role, no
overflow next pointer, and no materialized next image.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowVacuumTruncateRowsTest.php`
  - `1 test files, 221 assertions, 0 failures`
  - `67` PASS lines
- `php lanes/libsqlite/examples/wordpress-btree-overflow-vacuum-truncate-rows.php`
  - Reports copied `wp_options` overflow vacuum truncating pages
    `416, 415, 414, 413, 412`, including pointer-map page `414`.

## Non-Overlap

Avoids accepted overflow freeblock coalescing, overflow freelist release, bulk
overflow freeblocks, page relocation, root collapse, pointer-map page-move, and
WAL/VFS durability clusters. The new behavior is the truncation-window
accounting for the auto-vacuum pointer-map page that is not itself a released
overflow page.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`SQLiteDatabase`, freelist truncation, pointer-map, and overflow release
primitives already present in the libsqlite lane.
