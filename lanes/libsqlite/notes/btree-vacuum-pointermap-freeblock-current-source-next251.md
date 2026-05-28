# btree vacuum pointer-map freeblock current-source next251

## Behavior

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext251Plan`.
- Composes accepted `next248` seal rows and admits the next current-source cursor only after seal tokens match, pointer-map visibility is present, freeblock receipts are published, payload cursor advance is safe, and fenced tail pages remain excluded.
- The WordPress smoke models copied `wp_options` transient cleanup where obsolete overflow pages are vacuumed and reusable payload pages must not become the next write source until pointer-map/freeblock state is sealed.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext251Test.php`
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next251.php`
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext251Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext251Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next251.php`
- `git diff --check -- lanes/libsqlite`

## Non-overlap

This slice adds current-source cursor advancement admission after the accepted next248 publication seal. It does not repeat next248 sealing, next235 checkpoints, next232 handoff admission, overflow freelist release, page relocation, root collapse, bulk overflow freeblock materialization, or batch215 next248 behavior.

## Dependency Closure

No new support component is needed. The implementation reuses existing native B-tree page images, overflow deletion, pointer-map/freeblock seal rows, and current-source token chaining helpers.
