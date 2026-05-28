# B-tree Vacuum Pointer-Map Freeblock Current Source Next253

## Behavior

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext253Plan`.
- Composes accepted `next249` next-source allocation rows into grouped vacuum apply windows.
- Verifies each apply group opens with a pointer-map epoch before reusable freeblock/payload pages are exposed, repeated pointer-map page 105 starts a new group, leaf receipts remain ready at apply time, and truncated tail pages 109/110 remain fenced.
- WordPress smoke models copied `wp_options` transient deletion where obsolete overflow pages are vacuumed and the next writer can only reuse freeblock pages after pointer-map apply grouping is visible.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext253Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext253Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next253.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext253Test.php`
- Result: `1 test files, 1495 assertions, 0 failures` with 149 focused PASS lines.
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next253.php`
- Result: `wordpress-btree-vacuum-pointermap-freeblock-current-source-next253 self-test passed`

## Non-Overlap

This slice adds grouped vacuum apply windows after `next249` next-source allocation publication. It does not repeat `next249` source allocation ordering, `next245` cursor admission, `next248` publication sealing, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, accepted freelist/pointer-map reuse slices, or VFS/WAL behavior.

## Dependency Closure

No new support component is needed. The patch reuses existing native B-tree page parsing, overflow deletion, pointer-map metadata, freeblock receipts, and current-source next-source rows.
