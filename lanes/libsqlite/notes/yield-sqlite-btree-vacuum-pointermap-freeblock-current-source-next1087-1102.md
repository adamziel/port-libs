# SQLite b-tree vacuum pointer-map freeblock current-source next1087-1102

This slice extends `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan` without adding a numbered duplicate class. The next1087-1102 entrypoints reuse `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistCurrentSourceVariant`, continuing the next1071-1086 current-source handoff receipt pattern over the existing freelist splice rows.

Validation:

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceFreelistHandoffBatchFiveTest.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceFreelistHandoffBatchSixTest.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-freelist-handoff-batch-six.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceFreelistHandoffBatchFiveTest.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceFreelistHandoffBatchSixTest.php`
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-freelist-handoff-batch-six.php`
- `git diff --check`
