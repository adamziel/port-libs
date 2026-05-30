# B-tree Vacuum Pointer-Map Freeblock Current Source Next263-266

## Behavior

- Preserves the existing next263 freelist-splice coverage and adds public next264, next265, and next266 entry points.
- Reuses the next261 pointer-map-scoped vacuum finalization rows for each slice while issuing slice-specific action labels, status labels, row states, dependencies, and current-source tokens.
- Verifies the pointer-map trunk anchors, leaf slot pages, per-trunk slot ordinals, current-source write offsets, token continuity, and fenced-tail rejection across next264 through next266.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext263Test.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext264266Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next263.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next264-266.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext263Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext264266Test.php`
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next263.php`
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next264-266.php`
- `git diff --check -- lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext263Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext264266Test.php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next263.php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next264-266.php lanes/libsqlite/notes/yield-btree-vacuum-pointermap-freeblock-current-source-next263-266.md`

## Dependency Closure

No new support component is needed. Next263 through next266 reuse next261 vacuum finalization rows and add lane-local freelist splice receipt validation.

## Non-Overlap

This does not repeat next261 reusable-slot finalization, next259 source-next links, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior.
