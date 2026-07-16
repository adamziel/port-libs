# real-upstream-corpus-expression-affinity-dynamic-20260531T005632Z-0

Added `SQLiteRealUpstreamExpressionAffinityRealPrecisionDynamicTest.php` as an additive real upstream expression/affinity corpus shard.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
- `affinity2-600` and `affinity2-601`: large integer literals compared against values stored through REAL column affinity.

Coverage:

- 1,988 oracle-backed dynamic expression cases plus 1 ownership/source case.
- 1,989 focused TestRunner PASS lines.
- 1,993 focused assertions.
- The shard compares the PHP `SQLiteSelectSql` executor against local `sqlite3` for literal-vs-REAL-column and REAL-column-vs-literal comparisons across `<`, `<=`, `>`, `>=`, `=`, `==`, `<>`, and `!=`, including text literals and `CAST(text AS NUMERIC)`.

Non-overlap:

- This does not repeat accepted `affinity2-100..507` insertion/comparison/unary-blob behavior, `affinity3-100..260` REAL view/join affinity behavior, real expression cast/arithmetic matrices, expression `ORDER BY`, grouped SELECT text, JSON table source/cursor/constraint work, B-tree/WAL/VFS accepted clusters, or metadata-only runner rows.
- Values at the extreme PHP integer ceiling exposed a separate parser/compare precision gap around `9223372036854775296`; this shard keeps the accepted upstream `affinity2-600..601` precision family while avoiding that separate blocker for this handoff.

Dependency closure:

- No new support component is needed. This reuses the existing `sqlite3` oracle, `SQLiteRealExpressionAffinityCorpusPlan` affinity storage, and `SQLiteSelectSql` expression execution.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityRealPrecisionDynamicTest.php`
- Result: `1 test files, 1993 assertions, 0 failures`; `1989` PASS lines.
