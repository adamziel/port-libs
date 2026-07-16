# Source-Neutral Row-Value Savepoint Defaults Dynamic

Slice: source-neutral-src-rowvalue-savepoint-defaults-dynamic-20260601T180142Z-0

Status delta:
- Removed row-value/window production-source defaults and observable internals that hardcoded legacy option/id/value names in `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`.
- Added generic `setting_id`, `key_name`, and `key_value` defaults with dynamic column preservation for callers that pass alternate row-array schemas.
- Extended the no-domain guard to scan the row-value/window source file directly.

Focused evidence:
- `php -l` passed for changed PHP source/test files.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralCompoundWindowDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: 2 files, 25 assertions, 0 failures.
- `php tools/run-tests.php` over the focused row-value/window savepoint family passed: 24 files, 1578 assertions, 0 failures.
- `php lanes/libsqlite/examples/application-window-rowvalue-upsert-source-neutral-defaults.php --self-test` passed.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure:
- No new support component is needed. The slice reuses `SQLiteRowIdColumn` for generic row-id inference and the existing row-value/window savepoint helpers for behavior coverage.

Next task:
- Continue source-neutral cleanup for any remaining production-source domain terms in other libsqlite source files, keeping direct behavior tests and the no-domain guard green.
