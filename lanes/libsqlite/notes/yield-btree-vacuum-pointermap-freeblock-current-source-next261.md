# B-tree Vacuum Pointer-map Freeblock Current-source Next261

## Slice

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext261Plan`, a bounded current-source continuation over next258 that finalizes reusable freeblock pages into pointer-map-scoped vacuum batches.

The behavior preserves next258 handoff tokens and stale-slot fences, then proves that every reusable page has:

- an active pointer-map fence before finalization;
- a current-source-safe freeblock write offset;
- a finalized reusable slot grouped by pointer-map page;
- token-chain continuity back to the next258 handoff rows.

## Evidence

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext261Test.php
php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next261.php
php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext261Plan.php
php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext261Test.php
php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next261.php
git diff --check -- lanes/libsqlite
```

## Non-overlap

This slice does not repeat accepted next258 stale-slot fencing, next254 write-slot publication, next249 allocation rows, overflow freelist release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior.

## Dependency Closure

No new support component is needed. The implementation reuses the existing B-tree page, pointer-map, table-leaf, and next258 current-source handoff primitives.
