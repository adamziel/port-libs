# B-tree Vacuum Pointer-map Freeblock Current-source Next210

## Behavior

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext210Plan`.
- Builds on accepted next209 writer-source latch rows and materializes the next writer apply queue.
- Verifies pointer-map current-source pages are applied before payload/freeblock pages, writer tokens remain chained, current-source epochs are ready, and truncated tail pages 109/110 remain fenced.
- Application smoke models copied `wp_options` transient deletion where obsolete overflow pages are vacuumed and the next writer must not reuse payload pages before pointer-map state is visible.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext210Test.php`
- Result: `1 test files, 1139 assertions, 0 failures`
- PASS-line delta: `149`
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next210.php`
- Result: `application-btree-vacuum-pointermap-freeblock-current-source-next210 self-test passed`

## Non-overlap

This slice extends accepted next209 writer-source latch admission with writer apply ordering. It does not repeat next209 source latching, next206 sealing, next203 cursor batching, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, or accepted freelist/pointer-map reuse slices.

## Dependency Closure

No new support component is needed. The implementation reuses existing native next209 writer-source rows, pointer-map ordering, leaf freeblock receipts, and fenced-tail metadata.
