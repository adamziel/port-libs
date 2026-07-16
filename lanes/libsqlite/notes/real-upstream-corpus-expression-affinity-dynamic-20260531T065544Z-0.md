# real-upstream-corpus-expression-affinity-dynamic-20260531T065544Z-0

Added `SQLiteRealUpstreamExpressionAffinityInSelectDynamic20260531T065544ZTest.php` as an additive real upstream expression-affinity corpus shard.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
- Scenario ranges: `types2-7.*` and `types2-8.*`, covering `IN (SELECT...)` affinity comparison behavior without and with indexed left-side columns.

Implementation delta:

- `SQLiteSelectProjection` now carries projected column affinity metadata for column projections used by nested subqueries.
- `SQLiteSelectSql::execute()` strips that internal metadata from public top-level result rows while preserving it for correlated subquery execution.

Focused evidence:

- Red-first focused run before the projection metadata fix: `1 test files / 147 assertions / 26 failures`, reduced to `5 failures` after row fixture storage coercion, then fixed.
- Final focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityInSelectDynamic20260531T065544ZTest.php`
- Result: `1 test files / 147 assertions / 0 failures`
- Related guard: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityLikelyDynamic20260531T054224ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityParameterDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityInSelectDynamic20260531T065544ZTest.php`
- Result: `3 test files / 34473 assertions / 0 failures`

Countability:

- Focused PASS growth: `+139` TestRunner PASS cases from real upstream `types2.test`.
- Mapped denominator coverage remains `1589 / 1589`; this is additional PHP behavior coverage over already mapped upstream inventory.

Dependency closure:

- No new support component is needed. The slice reuses `SQLiteSelectSql`, `SQLiteSelectPredicate`, and `SQLiteAffinityComparison`; the only native behavior change is preserving projected column affinity metadata across subquery materialization.

Non-overlap:

- This does not repeat accepted `types2-1.*` through `types2-6.*` comparison and literal-list coverage, boolean truthiness, parameter-token expression coverage, planner-hint `likely()` coverage, expression `ORDER BY`, SELECT subqueries, LIKE/GLOB, MATCH/REGEXP, date affinity, JSON, WAL, VFS, B-tree, PRAGMA, trigger, or source-neutral cleanup batches.
