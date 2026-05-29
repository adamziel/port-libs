# SQLite b-tree vacuum pointer-map freeblock current-source next1167-1182

This slice extends `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan` without adding a numbered duplicate class. The next1167-1182 entrypoints reuse `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistCurrentSourceVariant`, continuing directly from the next1151-1166 current-source handoff receipt pattern over the existing freelist splice rows.

Validation:

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext11511166Test.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext11671182Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next1167-1182.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext11511166Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext11671182Test.php`
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next1167-1182.php --self-test`
- `git diff --check`
