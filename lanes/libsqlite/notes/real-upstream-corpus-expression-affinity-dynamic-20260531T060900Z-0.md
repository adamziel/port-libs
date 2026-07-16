# real-upstream-corpus-expression-affinity-dynamic-20260531T060900Z-0

Added `SQLiteRealUpstreamExpressionAffinityCollateDynamic20260531T060900ZTest.php` as an additive real upstream expression-affinity corpus batch.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Scenario range: `e_expr-9.1` through `e_expr-9.24`

Focused coverage:

- `12555` oracle-backed SQL cases plus one source/shape guard.
- `25121` focused assertions.
- `12556` focused TestRunner PASS lines.

Behavior covered:

- `COLLATE` binds to a comparison operand before `<`, `<=`, `>`, `>=`, `=`, `==`, `IS`, `!=`, `<>`, and `IS NOT`.
- Parenthesized comparison results are not re-compared under a trailing `COLLATE`.
- `BETWEEN` and `NOT BETWEEN` preserve the same operand-binding distinction.
- `BINARY`, `NOCASE`, and `RTRIM` collation behavior is checked against a local `sqlite3` oracle.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCollateDynamic20260531T060900ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCollateDynamic20260531T060900ZTest.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCollateDynamic20260531T060900ZTest.php`
  - `1 test files, 25121 assertions, 0 failures`

Non-overlap:

- This does not repeat accepted `types2`, `affinity2`, `affinity3`, CAST target-affinity, host-parameter, likely/unlikely, DQS, boolean truthiness, expression ORDER BY, LIKE/GLOB, Unicode GLOB, date affinity, JSON, WAL, VFS, B-tree, PRAGMA, trigger, or source-neutral cleanup batches.
- It owns the `e_expr.test` `e_expr-9.*` collation binding branch for this session.

Dependency closure:

- No new support component is needed. The slice reuses `SQLiteSelectSql`, existing native comparison/collation handling, and the local `sqlite3` oracle pattern already used by adjacent real upstream expression corpus tests.
