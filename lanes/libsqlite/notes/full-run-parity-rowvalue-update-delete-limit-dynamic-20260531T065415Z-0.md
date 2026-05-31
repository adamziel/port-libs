# Row-value UPDATE/DELETE LIMIT Dynamic Parity

Micro-slice: `full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T065415Z-0`

Base accepted HEAD: `598504695c988ec41a0063207004e700089f5af7`

## Behavior

`SQLiteUpdateDeleteReturningSql` now evaluates SQLite `randomblob(N)` in
constant `UPDATE` / `DELETE` `LIMIT` and `OFFSET` expressions. The evaluator
preserves the upstream SQLite rule that `randomblob(0)` and negative lengths
produce a one-byte blob, while non-integral lengths and malformed arity remain
rejected.

Focused matrix coverage adds deterministic `length(randomblob(...))` and
`length(hex(randomblob(...)))/2` windows over generic `app_settings` rows:

- outer UPDATE RETURNING ordered windows;
- row-value DELETE tuple subquery windows;
- parse-time checks for one-byte minimum behavior;
- malformed non-integral length and arity rejection.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/func.test`
  - `func-9.3` through `func-9.5` for `randomblob()` type/length behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test`
  - dynamic LIMIT expression acceptance and datatype mismatch behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test`

## Focused Evidence

Red check before the implementation:

- `php -r 'require "lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php"; require "lanes/libsqlite/src/SQLiteUpdateDeleteLimitPlan.php"; use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql; try { var_export(SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT length(randomblob(3))")); } catch (Throwable $e) { echo get_class($e), ": ", $e->getMessage(), "\n"; }'`
- Result: `InvalidArgumentException: SQLite UPDATE/DELETE LIMIT expressions must evaluate to an integer`

After this slice:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php`
- Result: `1 test files, 625 assertions, 0 failures`

Focused movement in this file is `+42` PASS cases and `+204` assertions.

## Non-overlap

This does not repeat accepted zeroblob, octet_length/hex, quote/typeof,
printf/format, length/substr/instr/replace/char/unicode, likelihood/iif,
math-scalar, row-value scalar-subquery, collation, LIKE/GLOB, or assignment
window coverage. It adds the distinct upstream `func.test` `randomblob()`
length family to the existing dynamic row-value UPDATE/DELETE LIMIT matrix.

## Dependency Closure

No new support component is needed. The existing bounded
`SQLiteUpdateDeleteReturningSql` constant-expression evaluator and
`SQLiteUpdateDeleteLimitPlan` selection helper are reused.
