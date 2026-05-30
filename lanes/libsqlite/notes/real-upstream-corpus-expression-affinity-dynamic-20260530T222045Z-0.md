# Real upstream expression affinity BETWEEN oracle batch

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T222045Z-0`

Accepted base: `2b1cf655ef1be10ae886e50a15d966f7036573f3`

Added `SQLiteRealUpstreamExpressionAffinityBetweenOracleDynamicTest.php`, a
real upstream SQLite corpus batch sourced from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
  `e_expr-13.*` BETWEEN precedence/comparison behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
  `affinity2-200..300` comparison-affinity and unary numeric coercion behavior.

The batch uses the local `sqlite3` binary as an oracle for 1,024 distinct
`BETWEEN` expressions spanning integer, real, text, blob, and NULL subjects and
bounds, then verifies `quote()`, `typeof()`, and NULL propagation through the
native PHP `SQLiteSelectSql` path. This is non-overlapping with the already
accepted e_expr-7 storage-class matrix, real arithmetic/cast oracle batches,
types2 affinity matrices, and static affinity2 row-comparison coverage.

Dependency closure: no new support component needed; this reuses existing
bounded `SQLiteSelectSql` expression parsing/execution plus the local sqlite3
oracle used by adjacent real upstream corpus tests.

Expected movement: `+1025` focused TestRunner PASS cases, behavior-backed by
real hydrated upstream SQLite test files. Mapped denominator remains complete
at `1589 / 1589`; this is selected PHP PASS-line growth, not new mapped
denominator growth.
