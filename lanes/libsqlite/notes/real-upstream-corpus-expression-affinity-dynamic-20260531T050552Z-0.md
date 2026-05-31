# real-upstream-corpus-expression-affinity-dynamic-20260531T050552Z-0

- Base accepted HEAD: `7d59ee97325649cafd2449deb321f30571bf474f`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`.
- Owned upstream range: `e_expr.test` `e_expr-23.1.1` through `e_expr-23.1.9` and `e_expr-24.1.1` through `e_expr-24.1.2`.
- Behavior added: simple `CASE base WHEN ...` comparison now inherits column collation metadata and CAST comparison affinity, matching SQLite `=` semantics for `NOCASE`, `RTRIM`, `INTEGER`, `REAL`, `NUMERIC`, `BLOB`, and `NULL` cases.
- Focused test growth: `235` focused TestRunner PASS cases / `241` assertions in `SQLiteRealUpstreamExpressionAffinityCaseBaseDynamic20260531Test.php`.
- Non-overlap: avoids accepted searched `CASE`/`iif()` truthiness, real arithmetic, real-prefix CAST, REAL IN, bitwise, BETWEEN, and boolean expression batches by targeting simple CASE base-expression comparison affinity/collation rules.
- Dependency closure: no new support component needed; reuses existing `SQLiteSelectSql`, `SQLiteSelectExpression`, `SQLiteAffinityComparison`, and sqlite3 as a local oracle for upstream parity.
