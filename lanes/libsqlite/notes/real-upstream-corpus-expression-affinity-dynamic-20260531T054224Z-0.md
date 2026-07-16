# real-upstream-corpus-expression-affinity-dynamic-20260531T054224Z-0

Added `SQLiteRealUpstreamExpressionAffinityLikelyDynamic20260531T054224ZTest.php` as an additive real upstream expression-affinity corpus batch.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereG.test`
- `whereG-7.0` through `whereG-7.2`: `likely()`, `unlikely()`, and `likelihood()` preserve expression values through SELECT projection and ordering.
- `whereG-8.1` through `whereG-8.10`: planner-hint wrappers participate in expression-affinity comparisons against text literals.
- `whereG-12.0`: `likely(a)` preserves REAL expression type behavior for REAL-valued operands.

Focused behavior:

- Builds a local `sqlite3` oracle from the hydrated upstream semantics.
- Verifies the native PHP `SQLiteSelectSql` executor over `1548` distinct dynamic cases plus one ownership/source citation case.
- Covers literal NULL/integer/REAL/text operands, row-sourced REAL/text/integer operands, comparison expressions, `coalesce()`/`nullif()`, nested planner-hint wrappers, and text-zero comparison affinity.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityLikelyDynamic20260531T054224ZTest.php`
- Result: `1 test files, 1558 assertions, 0 failures`
- Focused PASS-line movement: `+1549`

Non-overlap:

- This slice does not repeat accepted CASE/iif truthiness, expression range/membership, REAL arithmetic, overflow arithmetic, signed literal, CAST prefix, row-context expression, affinity2, affinity3 REAL joins, types2 subquery, expression ORDER BY, SELECT subquery, LIKE/GLOB, MATCH/REGEXP, date affinity, JSON, WAL, VFS, B-tree, PRAGMA, trigger, or source-neutral cleanup batches.
- It owns the `whereG.test` planner-hint expression-affinity branch for this session.

Dependency closure:

- No new support component is needed. The test reuses the existing native `SQLiteSelectSql` executor, `SQLiteCoreScalarFunction` planner-hint scalar functions, and the hydrated upstream `sqlite3` oracle.
