# B-tree Vacuum Pointer-Map Freeblock Current Source Next235

## Behavior

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext235Plan`.
- Builds on accepted `next232` current-source handoff rows.
- Adds post-handoff checkpoints that keep duplicate pointer-map rewrites visible before payload pages are marked reusable by the next writer.
- Verifies freeblock receipts remain visible, tail overflow pages stay fenced, current-source links close at EOF, and payload reuse waits for pointer-map admission.

## Application Smoke

- `examples/application-btree-vacuum-pointermap-freeblock-current-source-next235.php`
- Scenario: copied `wp_options` transient deletion vacuums overflow pages and checkpoints duplicate pointer-map page 105 rewrites before payload pages 3/106/107/108 are reusable.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext235Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext235Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next235.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext235Test.php`
- Result: `1 test files, 1404 assertions, 0 failures`
- PASS-line delta: `144`
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next235.php`
- Result: `application-btree-vacuum-pointermap-freeblock-current-source-next235 self-test passed`

## Non-Overlap

This slice adds post-handoff current-source checkpoints after `next232`. It does not repeat `next232` handoff admission, `next229` resume construction, `next224` cursor sequencing, overflow freelist release, page relocation, root collapse, bulk overflow freeblock materialization, or accepted freeblock/freelist reuse surfaces.

## Dependency Closure

No new support component is needed. The implementation reuses existing native B-tree, pointer-map, table-leaf, overflow, and current-source handoff helpers.
