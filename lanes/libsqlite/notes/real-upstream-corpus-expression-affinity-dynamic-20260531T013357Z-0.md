# real-upstream-corpus-expression-affinity-dynamic-20260531T013357Z-0

Added `SQLiteRealUpstreamExpressionAffinityRealCastPrefix20260531Test.php` as an additive real upstream expression/affinity corpus batch.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Scenario ranges: `e_expr-29.*`, `e_expr-30.*`, `e_expr-31.*`, and `e_expr-32.*`.

Focused behavior:

- 1,248 oracle-backed dynamic PASS cases for parser-level `SQLiteSelectSql` `CAST(...)` execution.
- Covers REAL, INTEGER, NUMERIC, and TEXT target casts over upstream-shaped text, blob, integer, real, NULL, whitespace, exponent, overflow, and no-prefix inputs.
- Checks `quote()`, `typeof()`, NULL propagation, zero comparison, negative comparison, and large-value comparison against local `sqlite3`.

Non-overlap:

- Does not repeat accepted `types2` predicate matrices, `affinity2/affinity3` comparison/view behavior, CASE affinity, BETWEEN, expression syntax, overflow arithmetic, broad CAST target storage-class coverage, Unicode GLOB, date affinity, JSON, pager/WAL, B-tree, PRAGMA, or runner metadata rows.
- The owned gap is upstream `e_expr.test` longest-prefix CAST and range behavior through parser-level SELECT execution, with real upstream scenario citations.

Dependency closure:

- No new support component is needed. This reuses the existing lane-local `SQLiteSelectSql` expression executor and local `sqlite3` oracle for verification.
