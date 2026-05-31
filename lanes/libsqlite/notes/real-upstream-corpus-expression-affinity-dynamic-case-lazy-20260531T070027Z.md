# Real upstream expression affinity CASE lazy corpus

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T070027Z-0`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Ported sections: `e_expr-25.1.1` through `e_expr-26.1.6`

Behavior covered:

- Searched `CASE` stops at the first true `WHEN`.
- Simple `CASE` stops at the first matching `WHEN`.
- Unchosen `WHEN`, `THEN`, and `ELSE` expressions are not evaluated.
- Dynamic nested simple/searched `CASE` expressions preserve SQLite `quote()` and `typeof()` results across integer, real, text, and NULL outputs.

Focused growth:

- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicCaseLazy20260531T070027ZTest.php`.
- The file owns `1200` distinct TestRunner PASS cases plus `2` metadata/dependency checks.
- Each behavior case compares native `SQLiteSelectSql` results to a local `sqlite3` oracle.

Non-overlap:

- This owns upstream `e_expr.test` lazy CASE evaluation sections `25` and `26`.
- It avoids accepted `e_expr-20..22` CASE truth/fall-through, `e_expr-23..24` CASE affinity/collation, CASE/iif truthiness, BETWEEN, LIKE/GLOB, CAST prefix, affinity2/affinity3/types2/types3, expression ORDER BY, SELECT subquery, JSON table, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup batches.

Dependency closure:

- No new support component is needed.
- The slice reuses native `SQLiteSelectSql`, `SQLiteSelectExpression` CASE short-circuit evaluation, JSON error behavior as an unchosen-branch tripwire, and local `sqlite3` oracle parity.
