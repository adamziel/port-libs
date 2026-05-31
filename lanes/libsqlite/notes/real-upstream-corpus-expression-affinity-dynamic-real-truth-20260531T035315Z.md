# Real Upstream Corpus: Expression Affinity Dynamic Real Truth

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T035315Z-0`

Accepted base: `9995fe4897b08d71e2d75db489dfa08c480a5292`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- `expr-14.1` through `expr-14.4`: SQL truth coercion for `OR`, `NOT NOT`, `CASE WHEN`, `count()`, and `sum()` over `0`, `1`, `NULL`, `0.5`, `'1x'`, and `'0x'`.
- `expr-15.*`: the same truth invariants after extra floating-point double bindings.

Patch summary:

- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealTruth20260531T035315ZTest.php`.
- Uses local `sqlite3` as the source oracle.
- Adds 1200 dynamic REAL/TEXT/NULL expression cases plus one ownership guard.
- Exercises `SQLiteSelectSql` constant-expression dispatch for `OR`, `AND`, `NOT`, `NOT NOT`, `CASE WHEN`, and the upstream expr-14 OR/CASE invariant.

Non-overlap:

- Does not repeat accepted real CAST prefix, overflow arithmetic, modulo, `IS DISTINCT`, unary-plus, aggregate truth, expr2 arithmetic, expression ORDER BY, JSON, WAL, VFS, or B-tree clusters.
- No production API or WordPress-specific source text was added.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealTruth20260531T035315ZTest.php`
- Result: `1 test files, 4807 assertions, 0 failures`; 1201 PASS lines.

Dependency closure:

- No new support component is needed. The shard reuses existing `SQLiteSelectSql` expression execution and the local `sqlite3` oracle already used by adjacent real-upstream corpus tests.
