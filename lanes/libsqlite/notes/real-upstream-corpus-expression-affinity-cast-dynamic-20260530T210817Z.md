# real-upstream-corpus-expression-affinity-cast-dynamic-20260530T210817Z

## Scope

- Ported real upstream SQLite `affinity2.test` sections `affinity2-400` through `affinity2-440`.
- Fixed row-array predicate comparison affinity propagation for `CAST(...)` expressions, so `CAST(c0 AS NUMERIC) > c1` applies the CAST expression affinity before comparing against text operands.
- Added `1003` focused PHP assertions in `SQLiteRealUpstreamExpressionAffinityCastComparisonDynamicTest.php`.

## Non-overlap

This slice extends the existing expression-affinity dynamic corpus without repeating the accepted `affinity2-100..300`, arithmetic, precedence, large cast matrix, LIKE/GLOB, or expression ORDER BY coverage. The new behavior targets the upstream false-negative path where a nonnumeric text value cast to NUMERIC compares greater than a negative text operand.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP `SQLiteSelectSql`, `SQLiteSelectExpression`, and `SQLiteSelectPredicate` row-array execution path.

## Verification

- `php -l lanes/libsqlite/src/SQLiteSelectPredicate.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCastComparisonDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCastComparisonDynamicTest.php` -> `1 test files, 1003 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCastDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCastComparisonDynamicTest.php` -> `4 test files, 14443 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
