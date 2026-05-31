# real-upstream-corpus-expression-affinity-dynamic-20260531T044751Z-0

Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicExpr2BooleanTest.php`
as an additive real upstream expression/affinity corpus batch.

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr2.test`
- Upstream scenarios `expr2-1.1` through `expr2-1.4.2`

Coverage:
- Ports the nested `IS FALSE` / `IS NOT FALSE` boolean-expression regression
  from `expr2.test`.
- Widens the same upstream expression family across dynamic truth terms,
  mixed numeric/text/NULL column values, column comparison branches, and
  result wrappers including `IS TRUE`, `IS NOT TRUE`, `IS NULL`, and
  `coalesce()`.
- Uses `sqlite3` as the oracle, then verifies the native PHP
  `SQLiteSelectSql` executor against the oracle for `quote()`, `typeof()`, and
  NULL-ness.

Focused growth:
- New focused TestRunner PASS cases: `6,001`.
- Behavior assertions: `18,013`.
- Mapped denominator coverage: unchanged at `1589 / 1589`; this is PASS-line
  growth from a real upstream SQLite corpus section, not new denominator
  inventory.

Non-overlap:
- This does not repeat accepted real-expression arithmetic/cast matrices,
  REAL `IN` affinity, `types2`/`types3` text-affinity probes, integer-boundary
  expression behavior, range-membership behavior, expression `ORDER BY`,
  grouped SELECT text, SQL subqueries, or JSON/B-tree/WAL/VFS clusters.
- The slice specifically owns the `expr2.test` nested boolean `IS TRUE/FALSE`
  expression-affinity family.

Dependency closure:
- No new support component is needed. The slice reuses the existing native
  `SQLiteSelectSql` expression evaluator, boolean truthiness, comparison,
  `quote()`, `typeof()`, and NULL propagation support.
