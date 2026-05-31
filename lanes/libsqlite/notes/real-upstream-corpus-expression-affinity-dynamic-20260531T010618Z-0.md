# real-upstream-corpus-expression-affinity-dynamic-20260531T010618Z-0

Added `SQLiteRealUpstreamExpressionAffinityDynamicCaseIifTest.php` as an additive real upstream expression-affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- `e_expr-21.*`: searched `CASE` truth evaluation, left-to-right branch choice, `ELSE` fallback, and NULL result without `ELSE`.
- `e_expr-22.*`: simple `CASE` branch comparison, first-match behavior, `ELSE` fallback, NULL result without `ELSE`, and ordinary equality semantics for `WHEN` arms.
- `e_expr-37.*`: `CASE`, `iif()`, and `if()` boolean truthiness for NULL, numeric zero, non-numeric text, numeric text, and non-zero numeric values.

Focused movement:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicCaseIifTest.php`
- Result: `1 test files, 52231 assertions, 0 failures`.
- PASS-line delta: `13057` focused PASS cases, including `13056` sqlite3-oracle expression rows plus the ownership guard.
- Status delta: local selected `phpPass` moves from `1429270` to `1442327` before integration.

Non-overlap:

- This shard does not repeat accepted expression precedence/operator batches, REAL literal/conversion, `types2`, `affinity2`, `affinity3`, NULL coalesce, BETWEEN/CASE, expression NULL logic, collation, LIKE/GLOB/ESCAPE, JSON, WAL, VFS, B-tree, trigger, date, pragma, or select corpus batches.
- The owned behavior is the real upstream `CASE`/`iif()`/`if()` truthiness and simple/searched branch-result matrix from `e_expr.test` sections `21`, `22`, and `37`, run through `SQLiteSelectSql` and checked against a local `sqlite3` oracle.

Dependency closure:

- No new support component is needed. The test reuses existing native PHP `SQLiteSelectSql`, CASE parsing/evaluation, conditional scalar functions, storage-class observation, and the existing local `sqlite3` oracle pattern used by adjacent real upstream expression-affinity dynamic tests.
