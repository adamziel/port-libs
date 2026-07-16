# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T104507Z-0

## Status

Ready for integration from accepted base `f9d9e6312c63dfc0751eedbcf238e9e6c2d6e7da`.

This handoff adds row-value UPDATE/DELETE LIMIT/OFFSET parity for SQLite `concat()` and `concat_ws()` scalar functions. It is sourced from upstream `func9.test` cases `func9-100` through `func9-160` and keeps the row-value/delete subquery behavior tied to `rowvalue4.test` and `limit.test`.

## Behavior

- Allows `concat()` and `concat_ws()` in dynamic UPDATE/DELETE LIMIT and OFFSET expressions.
- Preserves SQLite null handling: `concat(NULL, 3)` yields an integral text result, `concat(NULL)` yields non-integral empty text, `concat_ws(NULL, ...)` yields null and is rejected as a LIMIT integer.
- Preserves arity diagnostics for `concat()` and `concat_ws()` before the LIMIT integer coercion path.
- Adds 107 focused PASS cases and raises the changed parity test from 16206 to 16852 assertions.

## Verification

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` passed: `1 test files, 16852 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteReturningSqlTest.php` passed: `4 test files, 17556 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 3 assertions, 0 failures`.
- `php -r '$data=json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status.json OK\n";'` passed.
- `git diff --check -- lanes/libsqlite` passed.

## Non-Overlap

This slice does not repeat the accepted row-value LIKE/GLOB LIMIT/OFFSET parity, date/time scalar LIMIT/OFFSET parity, func7 math scalar LIMIT/OFFSET parity, `zeroblob()`/`randomblob()` LIMIT/OFFSET parity, or the existing `||` concatenation LIMIT/OFFSET coverage. The added upstream source truth is `func9.test` scalar function behavior.

## Dependency Closure

No new support component is needed. The existing `SQLiteCoreScalarFunction` implementation is reused for `concat()` and `concat_ws()`; this patch only wires it into the UPDATE/DELETE LIMIT expression evaluator and focused row-value parity tests.

## Root Harness

Not run - isolated micro-slice.
