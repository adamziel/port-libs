# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T150811Z-0

## Scope

- Lane: `libsqlite`
- Base accepted HEAD: `5042ee5a640251937d88ffe1e25c7b681010f72f`
- Behavior: row-value UPDATE/DELETE dynamic `LIMIT` and `OFFSET` now accept SQLite `iif()` / `if()` shorthand forms:
  - two-argument truth shorthand, e.g. `if(1, 2)`
  - variadic boolean/value pairs
  - optional default value
  - even-arity no-match `NULL`, which remains rejected when used directly as a LIMIT/OFFSET integer

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
  - `e_expr-37.6b` covers two-argument `if(1,'true')` truth shorthand.
- `/home/claude/port-libs/.upstream-cache/libsqlite/src/expr.c`
  - `sqlite3ExprIsIIF` documents `iif(x,y)` and CASE-equivalent handling for multi-argument forms.

## Non-overlap

This slice does not touch the currently pending row-value dynamic LIMIT surfaces for `random()`, JSON scalar functions, `unhex()`, scalar `like()` / `glob()`, parenthesis-free `current_date` / `current_time` / `current_timestamp`, or aggregate tuple subquery handling.

## Red-first Probe

Before the source edit:

```sh
php -r 'require "tools/bootstrap.php"; try { var_export(PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT if(1, 2)")); echo "\n"; } catch (Throwable $e) { echo get_class($e) . ": " . $e->getMessage() . "\n"; }'
```

Result:

```text
InvalidArgumentException: SQLite UPDATE/DELETE LIMIT if() needs three arguments
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
1 test files, 17889 assertions, 0 failures
4 test files, 18790 assertions, 0 failures
1 test files, 3 assertions, 0 failures
git diff --check -- lanes/libsqlite passed
```

Focused delta: `+105` TestRunner PASS cases and `+359` assertions in `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` (`17530 -> 17889`).

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing dynamic LIMIT expression evaluator, `sqliteTruthValue()`, and row-value UPDATE/DELETE executor.
