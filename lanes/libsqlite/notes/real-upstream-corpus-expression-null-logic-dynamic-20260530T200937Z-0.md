# real-upstream-corpus-expression-affinity-dynamic-20260530T200937Z-0

Status: ready for integration as a real upstream expression/null-logic corpus
batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Ported scenario range: `expr-1.58` through `expr-1.126b`.

Coverage added:

- NULL propagation through arithmetic, comparison, unary, and bitwise
  expression evaluation.
- `coalesce()` around NULL expression results.
- Scalar `min()`/`max()` returning NULL when any argument is NULL.
- Three-valued `AND`/`OR` behavior where TRUE can short-circuit NULL and FALSE
  can decide NULL.
- `BETWEEN` / `NOT BETWEEN` with NULL lower and upper bounds.
- `IS`, `IS NOT`, `IS DISTINCT FROM`, and `IS NOT DISTINCT FROM` in direct
  predicates and searched `CASE` branches.

Focused test growth:

- New focused TestRunner file:
  `lanes/libsqlite/tests/SQLiteRealUpstreamExpressionNullLogicDynamicCorpusTest.php`.
- The file is intentionally dynamic over generated generic `app`-style rows so
  it adds more than 1,000 distinct TestRunner PASS cases while citing one real
  upstream source range.

Non-overlap:

- Avoids accepted `types2.test`, `affinity2.test`, `affinity3.test`, operator
  precedence bulk, expression `ORDER BY`, SQL subquery, grouped SELECT, JSON,
  WAL, VFS, B-tree, and runner metadata-only clusters.
- This slice owns the remaining `expr.test` NULL logic and searched CASE
  truth-value matrix around `expr-1.58..1.126b`.

Dependency closure:

- No new support component is needed. The test reuses existing native PHP
  expression evaluation, scalar function dispatch, predicate evaluation,
  BETWEEN semantics, and searched CASE behavior.
