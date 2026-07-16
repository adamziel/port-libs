## real-upstream-corpus-expression-affinity-dynamic-20260601T080128Z-0

- Lane/session: `libsqlite` /
  `port-dev-sqlite-yield-dyn-real-expr-20260601T080128Z`.
- Accepted base: `924608cb5d0660a91dc7f34f65c3d602f24fd8e6`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
    `e_expr-6.1` through `e_expr-6.5`: `%` casts both original operands to
    INTEGER before computing the remainder.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
    `expr-13.2` through `expr-13.7`: text numeric operands use integer
    conversion when possible and REAL conversion for overflow/exponent forms.
- Behavior fixed: `SQLiteSelectExpression::numericValue()` now computes `%`
  with `integerOperand()` applied to the original evaluated operands, while
  preserving the existing numeric conversion values for SQLite storage-class
  selection. This fixes cases like `'1e20'%7`, which sqlite3 returns as
  `1.0` with storage class `real`; the prior PHP executor returned `0.0`.
- Focused corpus added:
  `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicModuloCast20260601T080128ZTest.php`.
  The test hydrates a sqlite3 oracle over 28 left operands, 9 right operands,
  and 5 expression contexts, including exponent text, overflow text, CAST, and
  BLOB operands.
- Focused result:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicModuloCast20260601T080128ZTest.php`
  passed with `1 test files, 5048 assertions, 0 failures`, adding 1261
  distinct TestRunner PASS cases.
- Adjacent regression checks:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealExprOperators20260531Test.php`
    passed with `1 test files, 8467 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityRealExpr2DynamicTest.php`
    passed with `1 test files, 6005 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicExplicitFloatText20260531Test.php`
    passed with `1 test files, 6256 assertions, 0 failures`.
- Non-overlap: this slice does not repeat accepted expression ORDER BY,
  scalar helper, NaN/Inf truth, explicit float-text arithmetic, e_expr
  operator baseline, or source-neutral API cleanup. It specifically owns the
  cross-rule `%` behavior between `e_expr-6` and `expr-13`.
- Dependency closure: no new support component is needed. The implementation
  reuses the existing SELECT SQL executor and expression evaluator; tests use
  the local `sqlite3` oracle only for expected values.
- Root harness: not run - isolated micro-slice.
