# B-tree Vacuum Pointer-Map Freeblock Current Source Next263

## Behavior

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext263Plan`.
- Builds on next261 pointer-map-scoped vacuum finalization and seals the finalized reusable pages into freelist splice receipts.
- Records trunk anchor pages, leaf slot pages, per-trunk slot ordinals, current-source write offsets, token continuity, and fenced-tail rejection so a copied `wp_options` transient delete cannot admit stale tail pages into the next freelist source.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext263Test.php` -> `1 test files / 1556 assertions / 0 failures` with 148 PASS lines.
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next263.php` -> `application-btree-vacuum-pointermap-freeblock-current-source-next263 self-test passed`.
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext263Plan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext263Test.php` -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next263.php` -> no syntax errors.
- `git diff --check -- lanes/libsqlite` -> clean.

## Dependency Closure

No new support component is needed. The slice reuses next261 vacuum finalization rows and adds lane-local freelist splice receipt validation.

## Non-Overlap

This does not repeat next261 reusable-slot finalization, next259 source-next links, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior.
