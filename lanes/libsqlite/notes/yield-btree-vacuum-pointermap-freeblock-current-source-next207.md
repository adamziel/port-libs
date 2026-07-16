# B-tree Vacuum Pointer-map Freeblock Current-source Next207

## Behavior

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext207Plan`, a final writer-window admission layer over the accepted next206 sealed current-source rows. The window records the exact pointer-map and payload/freeblock pages admitted to the next writer, preserves seal-token chaining, rejects stale admitted-page state, and keeps truncated tail pages fenced before reuse.

Application path: copied `wp_options` transient deletion with overflow pages 106-110, vacuum fencing of tail pages 109-110, and deterministic writer admission for pages `[2, 3, 105, 106, 107, 108]`.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext207Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext207Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next207.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext207Test.php`
  - `1 test files, 866 assertions, 0 failures`
  - 126 focused PASS lines
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next207.php`
  - `application-btree-vacuum-pointermap-freeblock-current-source-next207 self-test passed`

## Non-overlap

This is additive after next206 sealing: it does not repeat next206 seal rows, next203 cursor batching, next196 source-next handoff, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, or accepted freelist/pointer-map reuse slices.

## Dependency Closure

No new support component is needed. The slice reuses existing SQLite database/page parsing, table leaf delete, pointer-map metadata, next203 cursor batches, and next206 sealed pointer-map/freeblock rows.
