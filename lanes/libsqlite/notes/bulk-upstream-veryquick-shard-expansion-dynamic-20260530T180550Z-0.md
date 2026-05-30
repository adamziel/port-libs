# bulk-upstream-veryquick-shard-expansion-dynamic-20260530T180550Z-0

Base accepted HEAD: `70cbf38e6a31c3f41f86a2057096cb0006d09cf6`.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Scenario family: `e_expr-5.*` concatenation operator behavior, expanded into
  a non-overlapping literal matrix for NULL, text, integer, real, JSON-like,
  path-like, quoted, and Unicode operands.

## Delta

- Added `SQLiteRealUpstreamExpressionConcatBulkCorpusTest.php` with 1,024
  distinct TestRunner PASS cases.
- Fixed `SQLiteSelectSql` / `SQLiteSelectExpression` so REAL literals preserve
  SQLite-style text representation when used by `||`; examples include
  `0.0`, `5.0`, `1.25e2`, and `.75`.
- No mapped-denominator rows were added. Count this as PASS-line growth, not
  mapped coverage growth.

## Verification

- `php -l lanes/libsqlite/src/SQLiteSelectExpression.php`
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionConcatBulkCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionConcatBulkCorpusTest.php`
  - `1 test files, 1024 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicCastTest.php`
  - `2 test files, 6659 assertions, 0 failures`

## Non-overlap

This slice avoids the stale fabricated veryquick `next965-980` metadata path
and does not add runner-map or denominator rows. It exercises real upstream
`e_expr.test` behavior through the PHP SELECT executor and fixes the shared
literal concatenation behavior exposed by those cases.

## Dependency Closure

No new support component is needed. The existing bounded PHP SELECT SQL parser
and expression evaluator are reused.
