# B-tree Interior Split Current/Next 35

## Behavior

- Adds `SQLiteBTreeInteriorSplitCurrentNextPlan` for the upstream B-tree split path where a full table-interior current child is split into the current page plus a newly allocated next sibling.
- The plan materializes current/next page images, inserts the divider key into the parent, preserves parent right-most/current-child shape, and rewrites auto-vacuum pointer-map ownership for the new sibling and moved child pages.
- The Application smoke models a copied `wp_options` table-interior split during an insert/replacement path without requiring ext/sqlite.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeInteriorSplitCurrentNext35Test.php`
- `php lanes/libsqlite/examples/application-btree-interior-split-current-next35.php`

## Non-overlap

This avoids accepted root collapse, table/index page relocation, table/index interior merge, overflow freelist release, bulk overflow freeblock materialization, B-tree allocation pointer-map reuse, VFS writer/sync/lock/rollback clusters, WAL checkpoint/savepoint clusters, JSON table source/cursor/constraint clusters, Unicode GLOB, and SELECT SQL text/subquery/group/order clusters. The new surface is current-page table-interior split into a next sibling with parent divider insertion and pointer-map ownership rewrites.

## Dependency Closure

No new support component is needed. This reuses lane-local native PHP page assembly, table-interior cell parsing, SQLite database page reads, and auto-vacuum pointer-map mutation helpers.
