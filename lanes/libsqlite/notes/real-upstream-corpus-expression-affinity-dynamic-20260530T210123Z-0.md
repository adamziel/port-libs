# real-upstream-corpus-expression-affinity-dynamic-20260530T210123Z-0

Status: ready focused real-upstream behavior growth for expression affinity.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
- Ported the `types2-7.*` and `types2-8.*` `IN (SELECT...)` affinity family into
  `SQLiteRealUpstreamExpressionAffinityTypes2SubqueryDynamicTest.php`.
- The dynamic shard compares native `SQLiteSelectSql` rowid results against the
  local `sqlite3` oracle across integer, numeric, text, and blob-affinity
  subquery sources. The focused shard adds 1025 upstream-shaped TestRunner
  assertions.

Implementation:

- `SQLiteSelectSql` now reduces `IN (SELECT...)` values from visible projected
  columns only, ignoring hidden rowid, qualified source columns, and affinity
  metadata.
- `SQLiteSelectPredicate` now applies the left operand affinity and the
  subquery result-column affinity when comparing `IN (SELECT...)` candidates.
- Qualified `.__sqlite_column_affinities` metadata is accepted as internal row
  metadata during correlated subquery predicate evaluation.

Non-overlap:

- This does not repeat the accepted `types2.test` literal `IN (...)`,
  indexed equality/range, `affinity2.test`, `affinity3.test`, expression
  precedence/operator, expression `ORDER BY`, source-neutral CAST/LIKE/GLOB,
  or Unicode GLOB coverage. The owned gap is subquery-result affinity for
  `types2.test` `IN (SELECT...)`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/src/SQLiteSelectPredicate.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityTypes2SubqueryDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityTypes2SubqueryDynamicTest.php`
  - `1 test files, 1025 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityTypes2SubqueryDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicTypes2MatrixTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinity2DynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinity3DynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php`
  - `5 test files, 21300 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses native `SQLiteSelectSql`,
  `SQLiteSelectPredicate`, existing affinity metadata, and the local `sqlite3`
  oracle path already used by adjacent real-upstream expression affinity tests.
