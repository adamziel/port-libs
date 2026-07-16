# real-upstream-corpus-expression-affinity-dynamic-20260531T031254Z-0

Base accepted HEAD: `d3f35d53d135e23f73a270582d60d9916715bb54`.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Sections `e_expr-12.2.6` through `e_expr-12.2.8`.

Implemented behavior:

- `SQLiteSelectSql` now treats `CURRENT_TIME`, `CURRENT_DATE`, and
  `CURRENT_TIMESTAMP` as SELECT literal-value expressions.
- Added dynamic focused coverage for projection, `typeof`, `quote`, `LIKE`,
  `GLOB`, `BETWEEN`, `CASE`, `CAST`, and `substr` composition around those
  current-time literals.

Non-overlap:

- Does not repeat accepted signed numeric/string/blob literal rows, e_expr
  syntax-diagram row-context coverage, host parameter coverage, LIKE/GLOB
  wildcard shards, expression ORDER BY, GROUP BY text, or scalar subquery
  expression shards.
- This slice owns only the upstream `CURRENT_*` literal-value gap that prior
  signed-literal coverage explicitly excluded.

Verification:

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityCurrentTimeLiteralDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityCurrentTimeLiteralDynamicTest.php`
  - `1 test files, 98 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinitySignedLiteralDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealExprSyntaxTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityCurrentTimeLiteralDynamicTest.php`
  - `3 test files, 11129 assertions, 0 failures`

Dependency closure:

- No new support component needed. The slice reuses native SELECT expression
  literal parsing, UTC PHP clock formatting, text comparison, LIKE/GLOB,
  BETWEEN, CASE, CAST, and scalar `substr` evaluation.
