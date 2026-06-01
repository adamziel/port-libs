# full-run-parity-rowvalue-update-delete-limit-dynamic-20260601T074710Z-0

Scope: row-value `UPDATE` / `DELETE` dynamic `LIMIT` parity for
`CURRENT_DATE`, `CURRENT_TIME`, and `CURRENT_TIMESTAMP` literal-value
expressions.

Source truth and non-overlap:
- Upstream anchors: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
  `e_expr-12.2.6` through `e_expr-12.2.8` for the current date/time
  literal-value tokens, `limit.test` for LIMIT expression coercion, and
  `rowvalue4.test` for row-value tuple-source DML selection.
- This does not repeat accepted date/time modifier matrices, timediff dynamic
  LIMIT coverage, random/unistr/LIKE/GLOB/bind-parameter dynamic LIMIT
  coverage, or UPDATE/DELETE ordered LIMIT selection.

Red-first evidence:
- Before the source change, parsing
  `DELETE FROM app_settings RETURNING setting_id LIMIT length(CURRENT_DATE)-8`
  failed with `InvalidArgumentException: SQLite UPDATE/DELETE LIMIT arithmetic
  terms must be numeric`.
- Root cause: the dynamic LIMIT constant-expression evaluator did not treat
  bare current date/time tokens as scalar literal values, so nested functions
  such as `length(CURRENT_DATE)` could not produce an integer LIMIT.

Implementation:
- `SQLiteUpdateDeleteReturningSql` now resolves bare `CURRENT_DATE`,
  `CURRENT_TIME`, and `CURRENT_TIMESTAMP` through the existing
  `SQLiteCoreScalarFunction` current date/time implementation.
- Direct use of those text literals as LIMIT values still rejects as
  non-integer, matching SQLite's integer LIMIT coercion boundary.

Focused test movement:
- Added `SQLiteRowValueUpdateDeleteLimitCurrentTimeDynamicTest.php`.
- Added 24 UPDATE cases where outer LIMIT/OFFSET expressions use current
  date/time literal shape functions.
- Added 24 DELETE cases where row-value tuple subquery LIMIT/OFFSET
  expressions use current date/time literal shape functions.
- Added 10 parser and malformed guard cases for current literal length,
  `typeof()`, `substr()`/`unicode()`, `julianday()`, `date()`, and direct
  non-integer literal rejection.
- PASS/assertion delta: +327 focused assertions.

Verification:
- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitCurrentTimeDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitCurrentTimeDynamicTest.php`
  passed: `1 test files, 327 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitCurrentTimeDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitLikeGlobDynamicTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitRandomDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitUnistrDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBindParameterDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitTimediffDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed: `10 test files, 23687 assertions, 0 failures`.

Dependency closure:
- No new support component is needed. This reuses the existing native
  `SQLiteCoreScalarFunction` date/time literal implementation and the existing
  row-value UPDATE/DELETE LIMIT executor.
