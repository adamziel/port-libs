## real-upstream-corpus-expression-affinity-dynamic-real-expr-case-affinity-20260531T011058Z

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`.
- Ported section: `e_expr-23.1.6` through `e_expr-23.1.9` plus `e_expr-24.1.1` through `e_expr-24.1.2`, covering CASE base-expression comparison affinity and NULL handling.
- Behavior fix: `SQLiteSelectExpression` now applies column affinity metadata when comparing a CASE base expression to each WHEN expression, matching SQLite's rule that this comparison follows `=` affinity semantics while NULL base/WHEN values still do not match.
- Focused PASS growth: 1,201 TestRunner PASS cases in `SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealExprCaseAffinity20260531T011058ZTest.php`, backed by 1,200 oracle-generated CASE affinity cases and one ownership/source-truth case.
- Focused assertions: 3,608 in the new test file.
- Non-overlap: this does not repeat the accepted NULL/coalesce, real literal, real conversion, BETWEEN, CASE syntax/truth, affinity2/3, expression ORDER BY, or expression-index range-cost batches. It targets the previously missing CASE base comparison affinity rule from `e_expr-23.*`.
- Dependency closure: no new support component is needed; this reuses the existing `sqlite3` oracle convention for real upstream dynamic corpus tests and the existing row `__sqlite_column_affinities` metadata used by libsqlite focused tests.
