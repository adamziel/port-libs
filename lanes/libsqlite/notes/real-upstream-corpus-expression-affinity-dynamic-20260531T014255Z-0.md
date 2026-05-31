# real-upstream-corpus-expression-affinity-dynamic-20260531T014255Z-0

Status: focused real-upstream corpus behavior growth for expression truthiness and aggregate expression arguments.

Upstream source:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Scenario range: `expr-14.1` through `expr-14.4`

Behavior added:
- `SQLiteSelectSql` now detects aggregate functions nested inside scalar wrappers such as `quote(sum(...))` and `typeof(sum(...))`.
- `sum()`, `total()`, `avg()`, `min()`, `max()`, `count()`, and `group_concat()` can aggregate a scalar expression argument by materializing that expression into an internal aggregate value column before grouping.
- Added `SQLiteRealUpstreamExpressionAffinityTruthAggregateDynamicTest.php` with 1,200 sqlite3-oracle-backed PASS cases over dynamic REAL/TEXT/NULL rowsets for:
  - `count(*)` with `WHERE (x OR (8==9)) != (CASE WHEN x THEN 1 ELSE 0 END)`;
  - `count(*)` with `WHERE (x OR (8==9)) != (NOT NOT x)`;
  - `sum(NOT x) WHERE x`;
  - `sum(CASE WHEN x THEN 0 ELSE 1 END) WHERE x`.

Red-first evidence:
- Initial focused run failed with unsupported nested scalar `sum()` dispatch and missing materialized aggregate expression columns for `sum(NOT x)` and `sum(CASE...)`.
- After the source fix, the focused new test passed with `1 test files / 3605 assertions / 0 failures`.

Verification:
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityTruthAggregateDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityTruthAggregateDynamicTest.php`
  - `1 test files, 3605 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicCaseIifTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicBetweenCaseTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityTruthAggregateDynamicTest.php`
  - `3 test files, 59881 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

No-domain guard:
- `lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` is not present in this worktree, so the API guard was not run.

Non-overlap:
- This does not repeat accepted expression CASE/iif scalar truthiness, BETWEEN precedence, unbound/host parameter, bitwise, REAL-prefix conversion, REAL arithmetic, expression ORDER BY, grouped SELECT text, JSON table, VFS/WAL, B-tree, or source-neutral cleanup batches.
- The owned surface is parser/executor aggregate expression argument materialization for real upstream `expr.test` truth aggregate behavior.

Dependency closure:
- No new support component is needed. This reuses existing `SQLiteSelectSql`, `SQLiteSelectExpression`, `SQLiteSelectPredicate`, and `SQLiteGroupedAggregate` machinery.
