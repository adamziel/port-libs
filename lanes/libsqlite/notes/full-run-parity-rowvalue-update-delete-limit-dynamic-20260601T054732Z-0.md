# full-run-parity-rowvalue-update-delete-limit-dynamic-20260601T054732Z-0

Scope: row-value `UPDATE` / `DELETE` dynamic `LIMIT` parity for scalar
`like()` and `glob()` expressions.

Source truth and non-overlap:
- Upstream anchors: `e_expr.test` sections `15.1` and `17.3` for scalar
  `like(Y,X[,Z])` / `glob(Y,X)` dispatch behind LIKE/GLOB operators,
  `limit.test` for LIMIT/OFFSET expression parsing, and `rowvalue4.test` for
  row-value tuple-source LIMIT selection.
- This does not repeat accepted Unicode GLOB range behavior, LIKE/GLOB
  predicate/cursor planning, randomblob/zeroblob dynamic LIMIT, string
  `OFFSET` literal splitting, or UPDATE/DELETE ordered LIMIT selection.

Red-first evidence:
- Before the source change, parsing `DELETE FROM app_settings RETURNING
  setting_id LIMIT like('a%', 'abc')` failed with
  `InvalidArgumentException: SQLite UPDATE/DELETE LIMIT expressions must
  evaluate to an integer`.
- The dynamic LIMIT evaluator already had a native core scalar implementation
  for `like()` and `glob()` available through `SQLiteCoreScalarFunction`, but
  did not admit those functions in row-value UPDATE/DELETE LIMIT expressions.

Implementation:
- `SQLiteUpdateDeleteReturningSql` now admits scalar `like()` and `glob()` in
  dynamic LIMIT/OFFSET expressions.
- The evaluator preserves SQLite scalar function argument order
  `like(pattern, value[, escape])` and `glob(pattern, value)`, NULL
  propagation, escape validation, and integer result coercion.

Focused test movement:
- Added 28 UPDATE cases where outer dynamic LIMIT/OFFSET expressions combine
  scalar `like()` and `glob()`.
- Added 28 DELETE cases where row-value tuple subquery LIMIT/OFFSET expressions
  combine scalar `like()` and `glob()`.
- Added 10 parser and malformed guard cases for true/false LIKE/GLOB, escaped
  LIKE, comma-form LIMIT, NULL results, arity, and invalid escape length.
- PASS-line delta: +67 focused PASS cases in
  `SQLiteRowValueUpdateDeleteLimitLikeGlobDynamicTest.php`.

Verification:
- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitLikeGlobDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitLikeGlobDynamicTest.php`
  passed: `1 test files, 324 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitLikeGlobDynamicTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitRandomDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitUnistrDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBindParameterDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitTimediffDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed: `8 test files, 1873 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure:
- No new support component is needed. This reuses the existing native PHP core
  scalar function implementation and the existing row-value UPDATE/DELETE
  LIMIT parser/evaluator.
