# btree vacuum pointer-map freeblock current-source next256

## Behavior

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext256Plan`.
- Composes accepted `next251` cursor-admission rows and adds the next current-source publication fence: admitted pages become commit-ready only after pointer-map generations are published, freeblock receipts are visible, payload reuse has cursor admission, duplicate pointer-map pages preserve generation count, and fenced tail pages remain excluded.
- The Application smoke models copied `wp_options` transient cleanup where deleted overflow pages are vacuumed and reusable payload pages must not be published as current-source write inputs until pointer-map/freeblock state is commit-ready.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext256Test.php`
- Result: `1 test files, 1414 assertions, 0 failures` with 134 PASS lines.
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next256.php`
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext256Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext256Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next256.php`
- `git diff --check -- lanes/libsqlite`

## Non-overlap

This slice adds current-source next256 publication fencing after accepted next251 cursor admission. It does not repeat next251 admission, next248 sealing, next235 checkpoints, next232 handoff admission, overflow freelist release, page relocation, root collapse, bulk overflow freeblock materialization, accepted next250/next251 B-tree batch217 behavior, or suite evidence rows.

## Dependency Closure

No new support component is needed. The implementation reuses existing native B-tree page images, overflow deletion, pointer-map/freeblock admission rows, and current-source token chaining helpers.
