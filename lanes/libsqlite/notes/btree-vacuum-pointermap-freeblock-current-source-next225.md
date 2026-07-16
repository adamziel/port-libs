# B-tree Vacuum Pointer-map Freeblock Current-source Next225

## Behavior

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext225Plan`.
- Builds on accepted next219 current-source readback rows and admits a source-next publication ledger.
- Verifies pointer-map publication remains before payload/freeblock pages, source read tokens chain into new publication tokens, duplicate pointer-map rewrites stay visible, and truncated tail pages 109/110 remain fenced.
- Application smoke models copied `wp_options` transient deletion where obsolete overflow tail pages are vacuumed and only safe current-source pages are admitted for the next publication step.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext225Test.php`
- Result: `1 test files, 914 assertions, 0 failures`
- PASS-line delta: `122`
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next225.php`
- Result: `application-btree-vacuum-pointermap-freeblock-current-source-next225 self-test passed`

## Non-overlap

This slice extends accepted next219 current-source readback into source-next publication admission. It does not repeat next219 readback verification, next217 page-write materialization, next210 writer apply ordering, overflow freelist release, page relocation, root collapse, bulk overflow freeblock materialization, or accepted freelist/pointer-map reuse slices.

## Dependency Closure

No new support component is needed. The implementation reuses native next219 readback rows, token chains, pointer-map-before-payload ordering, duplicate rewrite metadata, and fenced-tail guards.
