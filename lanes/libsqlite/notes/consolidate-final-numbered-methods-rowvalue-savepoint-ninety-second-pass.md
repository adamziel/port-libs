# consolidate-final-numbered-methods-rowvalue-savepoint-ninety-second-pass

Consolidated the remaining plain DISTINCT row-value savepoint rollback direct
proof surface to stable descriptive test and WordPress example filenames.
Production behavior is intentionally unchanged: the accepted `next225` status,
savepoint default, dependency strings, receipt keys, and non-overlap metadata
remain observable aliases from the canonical implementation.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRowValueDistinctSubquerySavepointRollbackTest.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-distinct-subquery-savepoint-rollback.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueDistinctSubquerySavepointRollbackTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepoint*.php lanes/libsqlite/tests/SQLiteRowValueDistinctSubquerySavepointRollbackTest.php lanes/libsqlite/tests/SQLiteRowValueDistinctSubquerySavepointTest.php lanes/libsqlite/tests/SQLiteRowValueDistinctTupleSavepointTest.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-distinct-subquery-savepoint-rollback.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this cleanup reuses the
existing native PHP row-value UPDATE/DELETE RETURNING executor, SELECT DISTINCT
tuple source handling, and savepoint rollback/retry modeling.

Non-overlap: this is row-value savepoint proof-surface cleanup only. It avoids
rowvalue-window, pager/WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger,
suite evidence, and dashboard files, and it preserves accepted production
metadata values.
