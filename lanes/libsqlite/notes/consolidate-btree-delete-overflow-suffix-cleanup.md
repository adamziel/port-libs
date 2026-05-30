# B-tree Delete Overflow Suffix Cleanup

Date: 2026-05-30

Base: `d92f1bfbf573e86fd1ac05197813d64c1f646450`

Scope: consolidation-only cleanup for the B-tree delete-overflow materialization family.

Changes:

- Replaced production `SQLiteBTreeDeleteOverflowPlan` with stable `SQLiteBTreeDeleteOverflowPlan`.
- Renamed direct entry points to descriptive sequential delete names.
- Migrated direct b-tree delete-overflow tests and the WordPress smoke to stable file/class names.
- Updated the dependent overflow pointer-map plan/tests/examples to call the stable delete-overflow plan.
- Preserved observable action payloads such as `btree-delete-overflow-materialization-current-next`; no compatibility shim or numbered production class was left behind.

Verification:

- `php -l` for changed PHP source, tests, and examples: passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeDeleteOverflowMaterializationTest.php lanes/libsqlite/tests/SQLiteBTreeOverflowDeletePointerMapCurrentSourceNext86Test.php lanes/libsqlite/tests/SQLiteBTreeDeleteOverflowMaterializationCurrentNext75Test.php`: 3 files / 194 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTree*DeleteOverflow*Test.php lanes/libsqlite/tests/SQLiteBTreeOverflowDeletePointerMap*Test.php`: 5 files / 394 assertions / 0 failures.
- `php lanes/libsqlite/examples/wordpress-btree-delete-overflow.php`: passed.
- `php lanes/libsqlite/examples/wordpress-btree-overflow-delete-pointermap-current-source-next86.php`: passed.
- `git diff --check -- lanes/libsqlite`: passed.

Dependency closure: no new support component is needed; this patch reuses existing B-tree page, freelist, pointer-map, and record helpers.

Non-overlap: this does not touch accepted pager/WAL/VFS/STAT4/suite consolidation batches and does not claim new `phpPass` or mapped-coverage movement.
