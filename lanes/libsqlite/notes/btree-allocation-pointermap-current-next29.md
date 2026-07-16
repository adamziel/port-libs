# B-tree Allocation Pointer-Map Current/Next 29

This slice adds bounded application of a `SQLiteFreelistAllocationPlan` back
into a database image. The focused behavior covers an auto-vacuum database
allocating current/next freelist leaf pages for new B-tree pages, materializing
the allocated page images, decrementing the freelist trunk, and rewriting the
allocated pages' pointer-map entries from `free-page` to `btree-page`.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeAllocationPointerMapCurrentNext29Test.php`
- `Focused test run: 1 selected test files (root lock skipped)`
- `1 test files, 54 assertions, 0 failures`

Application smoke:

- `php lanes/libsqlite/examples/application-btree-allocation-pointermap-current-next29.php`
- Reports copied `wp_options` allocation reusing freelist leaves `[6,8]`,
  leaving `[4,7]` on the freelist, and preserving `PRAGMA integrity_check` as
  `ok` without ext/sqlite.

Non-overlap:

- Avoids accepted root-collapse, table/index page relocation, overflow
  freelist release, bulk overflow freeblocks, parent/interior merge, VFS
  writer/sync/lock/rollback, WAL checkpoint/savepoint, JSON table cursor/source
  and constraint, Unicode GLOB, and SELECT SQL text/subquery/group/order
  clusters. This is specifically allocation application of current/next
  freelist pages with pointer-map state after root-collapse and delete
  operations have already produced free pages.

Dependency closure:

- No new support component is needed. The patch reuses lane-local
  `SQLiteFreelistAllocationPlan`, `SQLiteDatabase` page-image replacement,
  freelist trunk parsing, pointer-map mutation, and integrity-check helpers.
