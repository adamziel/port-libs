# real-upstream-corpus-expression-affinity-dynamic-20260531T054737Z-0

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T054737Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Sections `expr-13.8` and `expr-13.9`, where SQLite enables DQS for DML and verifies that `"" <= ''` and `'' <= ""` both evaluate true.

Implemented behavior:

- `SQLiteSelectSql` now treats double-quoted expression tokens as string literals when they reach expression-literal parsing, including embedded doubled quote unescaping.
- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicDqs20260531T054737ZTest.php` with 580 real sqlite3-oracle-backed behavior cases plus one ownership case across DQS values, wrappers, comparisons, scalar functions, and embedded quote spellings.

Non-overlap:

- This owns only `expr.test` `expr-13.8`/`expr-13.9` DQS expression literal behavior.
- It avoids accepted REAL conversion, CASE/iif, integer boundary, logical truth, LIKE/GLOB, JSON, WAL, B-tree, VFS, and planner clusters.

Dependency closure:

- No new support component is needed; the slice reuses native `SQLiteSelectSql` expression dispatch and a local sqlite3 oracle for expected rows.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicDqs20260531T054737ZTest.php`
  - `1 test files, 1751 assertions, 0 failures`
  - `581` PASS lines
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicDqs20260531T054737ZTest.php`
  - no syntax errors
- `git diff --check -- lanes/libsqlite`
  - passed
