# B-tree Vacuum Pointer-map Freeblock Current-source Next258

## Behavior

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext258Plan`.
- Builds on accepted next254 page-local current-source freeblock write slots.
- Adds the next-source reusable-page handoff fence so stale current-source slots are fenced before reusable payload/overflow pages are consumed by the next source.
- Verifies pointer-map fences are visible before reuse, current-source tokens remain chained, next reusable pages have page-local slots, leaf freeblock receipts survive the handoff, and batch-size changes preserve the same reusable-page set.

## Application Smoke

- `examples/application-btree-vacuum-pointermap-freeblock-current-source-next258.php`
- Scenario: copied `wp_options` transient cleanup deletes an overflow-backed row, vacuums tail pages, and prevents the next import cursor from consuming reusable overflow pages until stale current-source freeblock slots are fenced.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext258Test.php`
- Result: `1 test files, 1402 assertions, 0 failures`
- PASS-line delta: `154`
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next258.php`
- Result: `application-btree-vacuum-pointermap-freeblock-current-source-next258 self-test passed`

## Non-overlap

This slice adds next-source reusable-page handoff and stale-slot fencing after next254 freeblock write-slot publication. It does not repeat next254 slot offsets, next249 allocation rows, next245 cursor admission, accepted next254/next255 B-tree vacuum pointer-map/freeblock behavior, overflow freelist release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior.

## Dependency Closure

No new support component is needed. The patch reuses lane-local B-tree, pointer-map, overflow, table-leaf, record, and current-source freeblock slot helpers.
