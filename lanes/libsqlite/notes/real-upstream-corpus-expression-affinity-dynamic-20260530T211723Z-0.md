# real-upstream-corpus-expression-affinity-dynamic-20260530T211723Z-0

Added `SQLiteRealUpstreamExpr2BooleanAffinityDynamicTest.php` as an additive
real upstream expression/affinity corpus batch.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr2.test`
- Upstream scenarios `expr2-1.1`, `expr2-1.2.1`, `expr2-1.2.2`,
  `expr2-1.3`, and `expr2-1.4.2`

Coverage:

- 1,250 sqlite3-oracle-backed dynamic boolean expression cases over the
  upstream `IS FALSE` / `IS NOT FALSE` nested expression family.
- 1 provenance/count assertion case.
- Focused TestRunner result: `1 test files, 6253 assertions, 0 failures`,
  with 1,251 PASS lines.

Non-overlap:

- This slice targets upstream `expr2.test` nested boolean truth-value
  expression behavior. It avoids accepted `expr.test` arithmetic/bitwise/NULL
  families, `types2.test` comparison and `IN` affinity matrices,
  `affinity3.test` view/join affinity coverage, expression `ORDER BY`, grouped
  SELECT text, JSON table source/cursor/constraint work, and metadata-only
  runner rows.

Dependency closure:

- No new support component is needed. The test reuses the existing native
  `SQLiteSelectPredicate` and `SQLiteSelectExpression` truth-value dispatch
  and uses the local `sqlite3` CLI only as an oracle for expected values during
  focused tests.
