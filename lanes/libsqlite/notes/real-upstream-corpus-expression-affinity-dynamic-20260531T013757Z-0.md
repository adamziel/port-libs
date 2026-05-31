# real-upstream-corpus-expression-affinity-dynamic-20260531T013757Z-0

Session: `port-dev-sqlite-yield-dyn-real-expr-20260531T013757Z`
Base accepted HEAD: `472430c1daaad1016852e97d68cabd3ea687d289`

Added `SQLiteRealUpstreamExpressionAffinityDynamicRealTextComparisonTest.php`
as an additive real upstream expression/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
  - `expr-4.10` through `expr-4.20`: REAL-affinity columns compare numeric-looking text numerically while plain TEXT columns retain text comparison semantics.

Focused coverage:

- `3,201` focused TestRunner PASS lines.
- `9,606` behavior assertions.
- Expected `phpPass` movement: `1524483 -> 1527684`.
- Mapped denominator movement: none. The upstream inventory is already mapped; this is countable PHP PASS-line growth over real hydrated upstream behavior.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicRealTextComparisonTest.php`
  - `1 test files, 9606 assertions, 0 failures`

Non-overlap:

- This shard targets `expr.test` REAL-affinity versus TEXT-affinity comparison semantics for `expr-4.10..4.20`.
- It does not repeat accepted expression overflow arithmetic, real arithmetic, real conversion, real precision, CASE/iif, NULL/coalesce, BETWEEN, LIKE/GLOB, cast-target, `types2`, SQL expression `ORDER BY`, grouped SELECT, JSON, WAL, VFS, pager, or B-tree clusters.
- It adds no metadata-only rows, generated fake upstream scripts, domain-specific APIs, or source-neutral compatibility wrappers.

Dependency closure:

- No new support component is needed. The batch reuses existing native `SQLiteSelectSql` execution and `SQLiteRealExpressionAffinityCorpusPlan` insert-affinity behavior with local `sqlite3` oracle evidence.
