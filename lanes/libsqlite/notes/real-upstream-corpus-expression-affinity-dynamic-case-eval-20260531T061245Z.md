# Real upstream corpus expression affinity dynamic CASE eval

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T061245Z-0`

Accepted base: `2139c8ce030e83a04c23079c17d6da80f20ffd83`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Sections `e_expr-20.1` through `e_expr-22.4.2`

Coverage added:

- New focused PHP corpus file:
  `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicCaseEval20260531T061245ZTest.php`
- Ports CASE expression searched/base evaluation semantics through
  `SQLiteSelectSql`, including truth handling, first matching branch
  selection, ELSE fall-through, NULL no-match fall-through, and base CASE
  equality behavior.
- Generates `5890` distinct sqlite3 oracle-backed CASE expressions and
  verifies PHP execution parity.
- Focused result: `1 test files, 17678 assertions, 0 failures`.

Non-overlap:

- This owns `e_expr.test` CASE evaluation sections `20` through `22`.
- It avoids the already-present `e_expr-23/24` CASE affinity/collation shard,
  `e_expr-1` precedence matrix, scalar subquery/EXISTS shards, affinity2,
  affinity3 REAL view/comparison shards, and accepted LIKE/GLOB/CAST
  expression files.

Dependency closure:

- No new support component needed.
- Reuses `SQLiteSelectSql` CASE parsing, `SQLiteSelectExpression` truth
  evaluation, and the local `sqlite3` oracle for hydrated upstream parity.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicCaseEval20260531T061245ZTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicCaseEval20260531T061245ZTest.php`
  - `1 test files, 17678 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - not run; focused path does not exist in this accepted base

Root harness: not run - isolated micro-slice.
