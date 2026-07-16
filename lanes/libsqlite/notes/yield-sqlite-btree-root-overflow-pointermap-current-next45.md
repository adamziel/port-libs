# B-tree Root Overflow Pointer-Map Current/Next45

This slice adds `SQLiteDatabase::planRootOverflowPageAllocation()` for the
auto-vacuum path where a payload stored on root b-tree page 1 needs overflow
pages. The existing `planOverflowPageAllocation()` guard for non-root callers is
preserved; the new root-specific entry point writes the first overflow
pointer-map parent as page 1 and keeps the remaining overflow chain parented to
the prior overflow page.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeRootOverflowPointerMapCurrentNext45Test.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 58 assertions, 0 failures`
- `php -d auto_prepend_file=tools/bootstrap.php lanes/libsqlite/examples/application-root-overflow-pointermap-current-next45.php`
  - emits copied `wp_options` root-btree overflow allocation with allocated
    pages `[3,104,5,107,206,106,209]`, appended page `[209]`, pointer-map pages
    `[2,105,208]`, and first-overflow parent page `1`.
- `php -l lanes/libsqlite/src/SQLiteDatabase.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeRootOverflowPointerMapCurrentNext45Test.php`
- `php -l lanes/libsqlite/examples/application-root-overflow-pointermap-current-next45.php`
- `git diff --check -- lanes/libsqlite`

## Non-Overlap

This does not repeat accepted root collapse, table/index page relocation,
overflow freelist release, bulk overflow freeblock materialization,
index-interior merge, VFS writer/sync/lock/rollback clusters, JSON table
cursor/source/constraint work, SELECT SQL text/subquery/group/order clusters,
Unicode GLOB, or WAL byte truncation. The new behavior is root page 1 as the
valid first-overflow pointer-map parent for newly allocated overflow chains.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
SQLite database image, freelist allocation, and pointer-map page-image helpers.
