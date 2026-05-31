# real-upstream-corpus-expression-affinity-dynamic-20260531T042958Z-0

Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicIntegerBoundary20260531T042958ZTest.php` as an additive real upstream expression-affinity corpus batch.

Upstream source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- `expr-11.1` through `expr-11.14`: integer-looking literal tokenization at the signed int64 boundary, including leading zeros, unary plus, unary minus, and REAL promotion past the boundary.

Focused coverage:
- 1,200 distinct TestRunner PASS cases from real upstream `expr.test` behavior.
- 6,007 focused assertions in the new test file.
- Dynamic matrix: 25 adjacent boundary magnitudes, 12 sign/leading-zero literal spellings, and 4 expression wrappers.
- Each row checks native `SQLiteSelectSql` parity against the local `sqlite3` oracle for `quote()`, `typeof()`, NULL result state, and self-comparison behavior.

Non-overlap:
- This does not repeat accepted arithmetic overflow promotion, REAL literal, CAST-prefix, string conversion, parameter, `types2`, affinity2/affinity3, LIKE/GLOB, NULL logic, truth aggregate, or expression ORDER BY batches.
- This slice owns the `expr.test` `expr-11.1..11.14` lexical integer-boundary behavior through dynamic constant SELECT dispatch.

Dependency closure:
- No new support component is needed. The slice reuses existing native `SQLiteSelectSql` constant expression dispatch, numeric literal parsing, `quote()`, `typeof()`, and comparison helpers.

Verification:
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicIntegerBoundary20260531T042958ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicIntegerBoundary20260531T042958ZTest.php`
- API guard: `lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` is not present in this worktree.
- `git diff --check -- lanes/libsqlite`
