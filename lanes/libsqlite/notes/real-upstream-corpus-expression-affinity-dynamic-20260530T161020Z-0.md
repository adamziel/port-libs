# real-upstream-corpus-expression-affinity-dynamic-20260530T161020Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
  scenarios `affinity2-200`, `affinity2-210`, `affinity2-220`,
  `affinity2-300`, and `affinity2-500` through `affinity2-507`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
  scenarios `expr-1.1` through `expr-1.5`, `expr-1.38` through
  `expr-1.46e`, `expr-1.56`, `expr-1.58`, `expr-1.61`, `expr-1.64`,
  `expr-1.67`, `expr-1.86` through `expr-1.95`, `expr-1.96` through
  `expr-1.99`, `expr-1.108`, and `expr-1.109`.

Behavior added:

- Adds `SQLiteRealUpstreamExpressionAffinityDynamicTest.php` with 79
  independent PHP TestRunner PASS cases covering dynamic unary affinity,
  numeric/text/blob comparison coercion, signed blob/text numeric conversion,
  arithmetic/null propagation, bit shifts, modulo/divide-by-zero NULL results,
  and BETWEEN/NOT BETWEEN UNKNOWN filtering.
- Uses only generic `app_settings` application rows. No new legacy CMS-shaped
  tables, APIs, examples, or fixture names are introduced.

Dependency closure:

- No new support component is needed. The tests reuse existing
  `SQLiteAffinityComparison`, `SQLiteSelectExpression`, `SQLiteSelectSql`,
  and `SQLiteBlobValue` behavior.

Verification:

- `php -l lanes/libsqlite/src/SQLiteAffinityComparison.php`
- `php -l lanes/libsqlite/src/SQLiteSelectExpression.php`
- `php -l lanes/libsqlite/src/SQLiteSelectPredicate.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicTest.php`
  passed with `1 test files, 85 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAffinityComparisonStorageClassCorpusTest.php lanes/libsqlite/tests/SQLiteCastAffinityComparisonCorpusTest.php lanes/libsqlite/tests/SQLiteExpressionOperatorCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicTest.php`
  passed with `4 test files, 352 assertions, 0 failures`.
