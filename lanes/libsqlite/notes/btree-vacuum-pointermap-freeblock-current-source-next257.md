# B-tree Vacuum Pointer-map Freeblock Current-source Next257

## Behavior

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext257Plan`.
- Builds on accepted `next253` grouped vacuum apply rows and records the narrower current-source advance fence after each pointer-map/freeblock group is durable.
- Verifies advanced pages match apply pages, pointer-map pages open their groups before reusable freeblock pages advance, leaf receipts are committed, source epochs move monotonically, and tail pages 109/110 remain fenced out of the next current source.

## Application Smoke

- `examples/application-btree-vacuum-pointermap-freeblock-current-source-next257.php`
- Scenario: copied `wp_options` transient cleanup deletes an overflow-backed row, vacuums obsolete overflow pages, and only advances the next current-source reader/writer after pointer-map and freeblock apply groups are fenced.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext257Test.php`
- Result: `1 test files, 1407 assertions, 0 failures`
- New focused PASS lines: `147`

## Non-overlap

This slice adds current-source advance fencing after `next253` grouped apply rows. It does not repeat `next253` grouped apply ordering, `next249` next-source allocation publication, `next248` seal construction, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, or WAL/VFS behavior.

## Dependency Closure

No new support component is needed. The patch reuses existing native B-tree vacuum/freeblock, pointer-map, table leaf, overflow-chain, and current-source apply primitives.
