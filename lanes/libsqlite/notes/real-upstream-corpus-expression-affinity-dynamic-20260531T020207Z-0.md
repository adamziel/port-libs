# real-upstream-corpus-expression-affinity-dynamic-20260531T020207Z-0

Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicIsDistinct20260531T020207ZTest.php` as an additive real upstream expression/affinity corpus shard.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Covered sections: `expr-1.111` through `expr-1.126`, including `IS`, `IS NOT`, `IS DISTINCT FROM`, `IS NOT DISTINCT FROM`, and equivalent `CASE WHEN` predicate dispatch around NULL/equal/non-equal pairs.

Focused PHP coverage:

- 3,200 oracle-backed dynamic behavior cases across 64 inserted row pairs, 5 affinity families (`INTEGER`, `REAL`, `NUMERIC`, `TEXT`, `BLOB`), and 10 upstream expression forms.
- 1 ownership/source case.
- 3,201 focused TestRunner PASS cases and 9,607 assertions.

Verification:

- Initial red run: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicIsDistinct20260531T020207ZTest.php` exposed 40 failures caused by the test oracle stringifying PHP `8.0` as SQL literal `8`.
- Fixed the oracle literal builder to preserve REAL-looking float literals as `8.0`, matching the lane-local inserted corpus.
- Passing focused run: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicIsDistinct20260531T020207ZTest.php` -> `1 test files, 9607 assertions, 0 failures`.

Non-overlap:

- This does not repeat accepted `types2` affinity matrices, `types3` text dual-representation coverage, CAST target-affinity dispatch, real-prefix conversion, CASE base-affinity, broad `e_expr-12.3` syntax matrix, overflow arithmetic, NULL/coalesce, LIKE/GLOB, expression `ORDER BY`, grouped SELECT text, JSON table source/cursor/constraints, or B-tree/WAL/VFS clusters.
- Count this as PASS-line/assertion growth only. Mapped denominator coverage remains `1589 / 1589`.

Dependency closure:

- No new support component is needed. This reuses the existing bounded `SQLiteSelectSql` executor, expression affinity insert coercion helper, and local `sqlite3` oracle pattern already used by real upstream expression corpus tests.
