# SQLite b-tree vacuum pointer-map freeblock current-source next1151-1166

This slice extends `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan` without adding a numbered duplicate class. The next1151-1166 entrypoints reuse `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistCurrentSourceVariant`, continuing directly from the next1135-1150 current-source handoff receipt pattern over the existing freelist splice rows.

Validation:

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext11351150Test.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext11511166Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next1151-1166.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext11351150Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext11511166Test.php`
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next1151-1166.php --self-test`
- `git diff --check`
