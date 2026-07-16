# Real Upstream Expression Affinity Dynamic Nullable IN Subquery

Micro-slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T151746Z-0`

Base accepted HEAD: `4678f572bda3b3437f0480f42476c787d671be75`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/in.test`
- Owned scenarios: `in-13.3` through `in-13.13`
- Behavior: NULL-aware `IN` / `NOT IN` against nullable and non-null
  subquery result columns, including correlated subqueries that project a
  qualified column such as `SELECT inside.a ...`.

## Patch

- `SQLiteSelectSql` now treats a lone qualified projection column as the
  scalar result column for `IN (SELECT ...)` subqueries while still ignoring
  hidden rowid and metadata keys.
- Added 1,000 sqlite3-oracle-backed dynamic TestRunner cases covering the
  upstream nullable subquery pattern with shifted value ranges.

## Non-Overlap

This avoids accepted `types2` IN affinity matrices, `in.test` `in-19`
REAL-affinity IN/equality behavior, `e_expr` CASE/EXISTS/scalar-subquery
coverage, expression `ORDER BY`, JSON, WAL, VFS, and B-tree clusters.

## Verification

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicInNullSubquery20260531T151746ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicInNullSubquery20260531T151746ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicInNullSubquery20260531T151746ZTest.php`
  - `1 test files, 8006 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityTypes2SubqueryDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityInSelectDynamic20260531T065544ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealInTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSubselectOrderLimitDynamicTest.php`
  - `4 test files, 25383 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`

`lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` is not present in
this worktree.

## Dependency Closure

No new support component is needed. The slice reuses `SQLiteSelectSql`
correlated-subquery execution and local `sqlite3` only as an oracle for the
focused PHP tests.
