# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T204122Z-0

## Scope

- Lane: `libsqlite`
- Base accepted HEAD: `91b42fe7029899440b4b46f38b3f903a76f3b322`
- Behavior: row-value UPDATE/DELETE dynamic `LIMIT` and `OFFSET` expressions now support SQLite postfix null predicates:
  - `expr ISNULL`
  - `expr NOTNULL`
  - `expr NOT NULL`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
  - `expr-3.25` through `expr-3.28` cover `isnull` and `notnull` expression predicates.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
  - Expression syntax inventory lists `EXPR ISNULL`, `EXPR NOTNULL`, and `EXPR NOT NULL`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` and `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test`
  - Dynamic LIMIT/OFFSET values are expression-derived integers before UPDATE/DELETE row selection.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test`
  - Row-value tuple subquery selection semantics remain the row-value source for the DELETE subquery windows.

## Non-overlap

This slice only adds postfix null predicate parsing in the dynamic LIMIT evaluator. It does not touch the pending same-family patches for `random()`, `unhex()`, scalar `like()` / `glob()`, `timediff()`, parenthesis-free `current_date` / `current_time` / `current_timestamp`, or aggregate tuple subqueries. It also preserves the existing `IS NOT NULL` comparison path.

## Red-first Probe

Before the source edit, `NOTNULL` was treated as an unsupported expression and rejected before integer coercion:

```sh
php <<'PHP'
<?php
require 'tools/bootstrap.php';
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

try {
    var_export(SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT 1 NOTNULL'));
    echo "\n";
} catch (Throwable $e) {
    echo get_class($e), ': ', $e->getMessage(), "\n";
}
PHP
```

Result:

```text
InvalidArgumentException: SQLite UPDATE/DELETE LIMIT expressions must evaluate to an integer
```

## Verification

```sh
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBindParameterDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
git diff --check -- lanes/libsqlite
```

Results:

```text
No syntax errors detected in lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php
1 test files, 20051 assertions, 0 failures
4 test files, 20952 assertions, 0 failures
1 test files, 3 assertions, 0 failures
git diff --check -- lanes/libsqlite passed
```

Focused delta: `+108` TestRunner PASS cases and `+754` assertions in `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` (`4875` PASS / `19297` assertions to `4983` PASS / `20051` assertions).

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing dynamic LIMIT predicate evaluator, nullable truth handling, and row-value UPDATE/DELETE executor.
