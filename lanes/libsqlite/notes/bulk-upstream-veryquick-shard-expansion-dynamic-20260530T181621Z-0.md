# bulk-upstream-veryquick-shard-expansion-dynamic-20260530T181621Z-0

- Base accepted HEAD: `a9928e604a7d849ecf8aa28f83049e71a24f4b05`.
- Owned upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`.
- Non-overlapping upstream sections: `e_expr-7.*` binary operator result storage-class matrix for `||`, `*`, `/`, `%`; and `e_expr-8.2.*` `IS` / `IS NOT` null-comparison matrix.
- Behavior fix: `SQLiteSelectExpression` now preserves SQLite's `real` storage class for `%` when either numeric operand is real while still integer-casting operands for the remainder operation.
- Focused growth: `1015` distinct TestRunner PASS cases in `SQLiteRealUpstreamEExprNullTypeBulkTest.php` (`1014` upstream behavior cases plus one ownership/count assertion), `1020` assertions total.
- Count type: PASS-line growth and behavior assertion growth. No mapped denominator movement.
- Dependency closure: reused the existing `sqlite3` CLI oracle pattern already used by focused real-upstream corpus tests; no new support component is needed.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamEExprNullTypeBulkTest.php` -> `1 test files, 1020 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteSelectExpression.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamEExprNullTypeBulkTest.php` -> no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status ok\n";'` -> `lane-status ok`.
- `git diff --check -- lanes/libsqlite` -> passed.
