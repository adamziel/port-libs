# consolidate-final-numbered-methods-rowvalue-savepoint-ninety-seventh-pass

Consolidated the row-value nested savepoint materialization proof
surface to stable descriptive test and Application example filenames. Production
behavior is intentionally unchanged: the accepted `next224` status, savepoint
defaults, dependency strings, receipt keys, and non-overlap metadata remain
observable from the canonical implementation.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRowValueNestedSavepointMaterializationTest.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-nested-savepoint-materialization.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueNestedSavepointMaterializationTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepoint*.php lanes/libsqlite/tests/SQLiteRowValueNestedSavepointMaterializationTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValue*Savepoint*Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-nested-savepoint-materialization.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this cleanup reuses the
existing native PHP row-value UPDATE/DELETE RETURNING executor and nested
savepoint rollback/retry modeling.

Non-overlap: this is row-value savepoint proof-surface cleanup only. It avoids
rowvalue-window, pager/WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger,
suite evidence, dashboard files, and production metadata changes.
