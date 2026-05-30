## B-tree Vacuum Current-Source Handoff Consolidation

Scope: `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan` current-source handoff summary/token/action labels and its direct B-tree tests/example.

Changes:
- Renamed the production handoff contract from numbered handoff labels to stable `current-source-handoff` labels.
- Renamed the direct test file to `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceHandoffVisibilityTest.php`.
- Renamed the WordPress smoke to `wordpress-btree-vacuum-pointermap-freeblock-current-source-handoff.php`.
- Updated B-tree downstream direct consumers in the next245 and next246 focused tests to read `current_source_handoff_token`.

Verification:
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceHandoffVisibilityTest.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext245Test.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext246Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-handoff.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceHandoffVisibilityTest.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext245Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext246Test.php`: 3 test files, 3990 assertions, 0 failures.
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-handoff.php`: self-test passed.

Dependency closure: no new support component needed; this is a naming consolidation over the existing B-tree vacuum current-source handoff behavior.
