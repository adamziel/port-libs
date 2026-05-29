# consolidate-final-numbered-methods-rowvalue-savepoint-eightieth-pass

Consolidated the row-value savepoint ordered-subquery and subquery-limit direct
proof surfaces to stable descriptive test/example filenames. Production
behavior is intentionally unchanged: status strings, dependency strings, phase
labels, savepoint names, and result keys still preserve the accepted observable
values.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRowValueOrderedSubquerySavepointRetryTest.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueSubqueryLimitSavepointRetryTest.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-ordered-subquery-savepoint-retry.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-subquery-limit-savepoint-retry.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueOrderedSubquerySavepointRetryTest.php lanes/libsqlite/tests/SQLiteRowValueSubqueryLimitSavepointRetryTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepoint*.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-subquery-limit-savepoint-retry.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this cleanup reuses the
existing row-value UPDATE/DELETE RETURNING executor, ordered subquery tuple
handling, and savepoint current-source modeling.

Non-overlap: avoids rowvalue-window and preserves rowvalue-savepoint production
metadata. This is file/proof-surface cleanup only for the ordered-subquery and
subquery-limit retry pair.
