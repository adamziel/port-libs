# B-tree Vacuum Pointer-Map Freeblock Current Source Next203

## Scope

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan` as a current-source next-writer cursor admission layer after the existing next196 source-next handoff.
- The cursor admits only pages whose pointer-map dependency pages and payload pages are carried from the current source, keeps the secure-delete leaf freeblock receipt ready for the next writer, and blocks fenced truncated tail pages from the cursor.
- WordPress smoke covers deleting an overflow-backed copied `wp_options` transient and verifying that the next writer cursor sees pointer-map pages `2`/`105`, payload pages `3`/`106`/`107`/`108`, and no truncated tail pages.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext203Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next203.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext203Test.php`
  - `1 test files, 707 assertions, 0 failures`
  - `113` focused PASS lines
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next203.php`
  - `wordpress-btree-vacuum-pointermap-freeblock-current-source-next203 self-test passed`

## Non-Overlap

This slice extends the accepted next196 source-next handoff with cursor admission. It does not repeat next196 token handoff, next192 validation, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, accepted freelist/pointer-map reuse, or other queued B-tree surfaces.

## Dependency Closure

No new support component is needed. The slice reuses existing native B-tree page images, pointer-map metadata, source-next handoff tokens, secure-delete leaf freeblock receipts, and fenced-tail metadata.
