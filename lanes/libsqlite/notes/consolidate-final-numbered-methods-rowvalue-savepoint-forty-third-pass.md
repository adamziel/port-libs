# Row-Value Savepoint Consolidation Forty-Third Pass

Consolidated three row-value UPDATE/DELETE RETURNING savepoint `OR FAIL`
retry caller surfaces that still used worker-numbered direct test and example
names.
The direct tests and Application examples now use stable descriptive filenames:

- `SQLiteRowValueOrFailSavepointRetryTest.php`
- `SQLiteRowValuePreFailRollbackRetryTest.php`
- `SQLiteRowValueFailStatementRetryTest.php`
- `application-rowvalue-or-fail-savepoint-retry.php`
- `application-rowvalue-pre-fail-rollback-retry.php`
- `application-rowvalue-fail-statement-retry.php`

The canonical production class remains
`SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan`; the touched
production result tokens, dependency tags, savepoint defaults, test labels, and
example labels were renamed from numeric worker tokens to descriptive
`or-fail-savepoint-retry`, `pre-fail-rollback-retry`, and
`fail-statement-retry` tokens.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueOrFailSavepointRetryTest.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValuePreFailRollbackRetryTest.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueFailStatementRetryTest.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-or-fail-savepoint-retry.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-pre-fail-rollback-retry.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-fail-statement-retry.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueOrFailSavepointRetryTest.php lanes/libsqlite/tests/SQLiteRowValuePreFailRollbackRetryTest.php lanes/libsqlite/tests/SQLiteRowValueFailStatementRetryTest.php`
  -> `3 test files, 219 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rowvalue-or-fail-savepoint-retry.php --self-test`
- `php lanes/libsqlite/examples/application-rowvalue-pre-fail-rollback-retry.php --self-test`
- `php lanes/libsqlite/examples/application-rowvalue-fail-statement-retry.php --self-test`
- Touched production/test/example worker-token scan: no matches for the removed
  direct caller tokens
- Protected numbered-suffix scan across `src`, `tests`, and `examples`
  -> `0`

Dependency closure: no new support component is needed; this is a
consolidation-only cleanup over existing native row-value, savepoint, and
RETURNING behavior.
