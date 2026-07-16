# Row-Value UPDATE/DELETE Dynamic LIMIT Random Parity

Date: 2026-06-01
Base accepted HEAD: dc8bb5cac377111467dc403c9b9c75704db62cd4
Micro-slice: full-run-parity-rowvalue-update-delete-limit-dynamic-20260601T020206Z-0

## Source Truth

- SQLite upstream `test/func.test` func-9.1 and func-9.2 verify that `random()` is non-NULL and returns an integer.
- SQLite upstream `src/func.c` `randomFunc()` is the source implementation for the zero-argument random integer scalar.
- Existing row-value UPDATE/DELETE LIMIT parity in this lane continues to cite upstream `limit.test`, `e_update.test`, `e_delete.test`, and `rowvalue4.test` for ordered LIMIT/OFFSET mutation semantics.

## Behavior Added

- `SQLiteUpdateDeleteReturningSql` now allows `random()` inside dynamic UPDATE/DELETE LIMIT and OFFSET expressions.
- The evaluator enforces SQLite-compatible zero-argument arity for `random()` and routes the value through the existing `SQLiteCoreScalarFunction` implementation.
- Focused tests exercise deterministic upstream-backed wrappers around the volatile value: `typeof(random()) = 'integer'`, `random() IS NOT NULL`, range predicates, postfix `NOTNULL`, and direct integer LIMIT evaluation.

## Evidence

- Red-first before the source fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitRandomDynamicTest.php`
  failed with `1 test files, 39 assertions, 53 failures` because dynamic LIMIT/OFFSET rejected or mis-evaluated `random()`.
- Focused after the source fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitRandomDynamicTest.php`
  passed with `1 test files, 281 assertions, 0 failures`.
- Adjacent family plus no-domain guard:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitRandomDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBindParameterDynamicTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitUnistrDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitTimediffDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed with `8 test files, 22248 assertions, 0 failures`.

## Non-Overlap

This slice does not repeat the accepted row-value dynamic LIMIT work for `unhex()`, postfix null predicates, `unistr()`, `unistr_quote()`, `timediff()`, `randomblob()`, or `zeroblob()`. The new covered surface is `random()` zero-argument scalar dispatch in row-value UPDATE/DELETE LIMIT and OFFSET expressions.

## Dependency Closure

No new support component is needed. The patch reuses the existing scalar-function dispatcher and row-array UPDATE/DELETE LIMIT executor.
