# SQLite b-tree vacuum pointer-map freeblock current-source next911-926

Prepared next911-926 as the direct follow-on to completed next895-910 by extending the canonical
`SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan` wrapper/bounds range through next926.

Coverage stays consolidated in the existing B-tree vacuum pointer-map/freeblock current-source class;
no numbered source class was added.

Validation:

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext911926Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next911-926.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext895910Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext911926Test.php`
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next895-910.php`
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next911-926.php`
- `git diff --check`
