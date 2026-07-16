# Real upstream expression affinity NULL coalesce corpus

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T005225Z-0`

Base accepted HEAD: `452a6f6fbb9dca50b40370a18b13b7d77ca03385`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- `expr-1.58` through `expr-1.77`: `coalesce()` around NULL-propagating arithmetic, comparison, unary, and bitwise expressions.
- `expr-1.78` through `expr-1.85`: three-valued logical expressions and scalar `min()`/`max()` NULL propagation.

Added PHP coverage:

- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicNullCoalesce20260531T005225ZTest.php`
- 2,592 dynamic SQLite-oracle expression cases plus one ownership/count test.
- 10,374 focused assertions.
- Expected selected PASS-line movement: `+2593`.

Non-overlap:

- Avoids accepted expression BETWEEN, bitwise-only, cast-target, real-conversion, row-context comparison, types2 matrix, expression ORDER BY, grouped SELECT, JSON, WAL, VFS, and B-tree clusters.
- This slice specifically exercises upstream `expr.test` NULL expression propagation through `SQLiteSelectSql` constant SELECT expression dispatch.

Dependency closure:

- No new support component is needed. The shard reuses the existing lane-local `SQLiteSelectSql` expression evaluator and the local `sqlite3` binary only as a test oracle.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicNullCoalesce20260531T005225ZTest.php`
- Result: `1 test files, 10374 assertions, 0 failures`
