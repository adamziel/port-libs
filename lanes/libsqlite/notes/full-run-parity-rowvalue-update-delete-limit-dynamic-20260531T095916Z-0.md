# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T095916Z-0

## Scope

Implemented host-parameter binding for `SQLiteUpdateDeleteReturningSql` so
`UPDATE`/`DELETE ... RETURNING ... ORDER BY ... LIMIT/OFFSET` plans and
row-value tuple subqueries can evaluate SQLite-style bound parameters before
dynamic LIMIT/OFFSET expression parsing.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test`
  `limit-10.1` and `limit-10.2` host-parameter LIMIT/OFFSET cases.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test`
  row-value subselect LIMIT/OFFSET cases around tuple `IN` and comparison.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test`
  `e_update-3.3` UPDATE LIMIT/OFFSET behavior.

Non-overlap:

- This does not repeat the accepted `func7.test` math-scalar LIMIT/OFFSET
  slice. It covers host bind parameters (`?`, `?NNN`, `:name`, `@name`,
  `$name`) feeding UPDATE/DELETE LIMIT expressions and row-value subqueries.
- Lane status records the exact +42 focused PASS-line delta from the new test
  file; no dashboard/progress files were edited.

## Red-First Evidence

Before the implementation, a bound LIMIT parameter was treated as an unbound
expression token and rejected:

```text
php -r 'require "lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php"; require "lanes/libsqlite/src/SQLiteUpdateDeleteLimitPlan.php"; require "lanes/libsqlite/src/SQLiteSelectResult.php"; use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql; try { SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT :limit", [":limit" => 2]); echo "unexpected pass\n"; } catch (Throwable $e) { echo get_class($e).": ".$e->getMessage()."\n"; }'
InvalidArgumentException: SQLite UPDATE/DELETE LIMIT expressions must evaluate to an integer
```

## Verification

```text
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
No syntax errors detected in lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php

php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBindParameterDynamicTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBindParameterDynamicTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBindParameterDynamicTest.php
1 test files, 250 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBindParameterDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php
3 test files, 16433 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
1 test files, 3 assertions, 0 failures

php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "valid json\n";'
valid json

git diff --check -- lanes/libsqlite
passed with no output
```

## Dependency Closure

No new support component is needed. The patch reuses the existing
`SQLiteUpdateDeleteReturningSql`, `SQLiteUpdateDeleteLimitPlan`,
`SQLiteSelectResult`, and `SQLiteBlobValue` surfaces and adds bounded
parameter literalization inside the existing parser path.
