# Real Upstream Corpus Expression Affinity Dynamic Real Expr Operators

- Session: `port-dev-sqlite-yield-dyn-real-expr-20260531T053730Z`
- Micro-slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T053730Z-0`
- Base accepted HEAD: `4492e9529d6540daf2941a27323f36260b8cf64c`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Upstream sections: `e_expr-2.*` through `e_expr-7.*`

## Delta

Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealExprOperators20260531Test.php`.

The test dynamically generates 1,692 real expression cases from upstream unary, concatenation, arithmetic, modulo, and bitwise operator sections. Each case compares `quote(...)`, `typeof(...)`, and NULL propagation from `SQLiteSelectSql` against the local `sqlite3` oracle. The final ownership test records the upstream section and corpus dimensions.

Focused movement:

- PASS lines: `+1693`
- Assertions: `8467`
- Production source: unchanged
- Mapped denominator: unchanged, already `1589 / 1589`

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealExprOperators20260531Test.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealExprOperators20260531Test.php`

Result: `1 test files, 8467 assertions, 0 failures`.

## Non-Overlap

This avoids accepted real expression CAST-prefix, real expression binary comparison, affinity2/types2, affinity3 REAL join, expression ORDER BY, SELECT grouped text, JSON, WAL, B-tree, VFS, PRAGMA, trigger, and UPSERT clusters. It specifically covers `e_expr.test` unary and binary operator storage-class behavior across dynamic REAL/text/numeric operands.

## Dependency Closure

No new support component is needed. The test reuses the existing native `SQLiteSelectSql` expression evaluator and the local `sqlite3` oracle pattern already used by upstream corpus tests.
