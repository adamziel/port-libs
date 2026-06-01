# real-upstream-corpus-expression-affinity-dynamic-20260601T040657Z-0

Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicInRhsAffinity20260601T040657ZTest.php` as an additive real upstream expression/affinity corpus batch.

Source truth from hydrated upstream SQLite:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/in.test`
- Ported section: `in-11.1..11.6`
- Behavior: the left operand's NUMERIC affinity is applied to literal values on the right side of `IN (...)`, while unary `+` strips the left operand affinity and no-affinity columns do not coerce text RHS values.

Focused coverage:

- 1002 new focused TestRunner PASS cases.
- 6008 behavior/source assertions.
- `phpPass` delta: 5436032 -> 5437034.
- The corpus uses 1000 oracle-backed dynamic datasets over integer, decimal, leading-zero, and exponent numeric strings.

Verification:

- Initial focused run exposed a test-oracle parser bug only: an empty final sqlite3 row-list lost its trailing tab during `trim()`.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicInRhsAffinity20260601T040657ZTest.php`
  - `No syntax errors detected`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicInRhsAffinity20260601T040657ZTest.php`
  - `1 test files, 6008 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityInListDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityInSelectDynamic20260531T065544ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicInNullSubquery20260531T151746ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicInRhsAffinity20260601T040657ZTest.php`
  - `4 test files, 25365 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 4 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Non-overlap:

- Owns only upstream `in.test` `in-11` RHS affinity and unary-plus affinity stripping.
- Avoids accepted `types2` `IN (...)` and `IN (SELECT...)` matrices, `in.test` `in-19` REAL-affinity `IN`, `affinity2` storage/comparison matrices, `e_expr` CASE/CAST/BETWEEN/LIKE/GLOB/EXISTS sections, JSON, WAL, VFS, B-tree, PRAGMA, trigger, release-runner, and source-neutral cleanup batches.

Dependency closure:

- No new support component is needed. The slice reuses existing native `SQLiteSelectSql` IN-list parsing, `SQLiteSelectPredicate` affinity comparison, unary-plus expression evaluation, and the local `sqlite3` oracle path used by adjacent real upstream expression-affinity tests.

Root harness: not run - isolated micro-slice.
