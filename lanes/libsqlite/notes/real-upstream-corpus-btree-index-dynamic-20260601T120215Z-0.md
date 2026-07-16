# real-upstream-corpus-btree-index-dynamic-20260601T120215Z-0

Status: ready for integration after focused verification.

Base accepted HEAD: `5b3a92fac14e00372ad9ece599226a1c8024ea79`.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wherelimit3.test`
- Upstream sections covered: `wherelimit3-1.1`, `wherelimit3-1.2`, `wherelimit3-1.3`, and `wherelimit3-1.4`.

Implemented coverage:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::whereLimit3RangeLimitPlannerCases(1000)`.
- Added `SQLiteRealUpstreamBtreeWhereLimit3RangeDynamicTest.php`.
- Covers LIMIT-sensitive planner behavior where a positive LIMIT chooses `SEARCH t1 USING INDEX t1a (a>? AND a<?)` plus `USE TEMP B-TREE FOR ORDER BY`, while `LIMIT -1` under STAT4 chooses `SCAN t1 USING INDEX t1b`.
- Adds 1003 focused TestRunner PASS cases from real upstream `wherelimit3.test` behavior.

Non-overlap:

- This owns upstream `wherelimit3.test` query-plan LIMIT behavior only.
- It avoids accepted `where.test` section-1, `where2` through `whereN`, `whereB`, `where5`, `wherelimit.test` update/delete LIMIT parity, B-tree page relocation/root-collapse/overflow/freelist release, VFS writer/sync/lock, rollback-commit, JSON, PRAGMA, trigger/FK, row-value, SELECT expression ORDER BY/GROUP/subquery, and source-neutral cleanup clusters.

Dependency closure:

- No new support component is needed.
- Reuses the lane-local B-tree/index dynamic corpus plan infrastructure for STAT4 limit-costing metadata, range-index detail, order-index detail, and result-row bounds.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereLimit3RangeDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereLimit3RangeDynamicTest.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereLimit3RangeDynamicTest.php`
  - `1 test files, 45510 assertions, 0 failures`
  - PASS cases: `1003`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php`
  - `1 test files, 65169 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 5 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output.

Root harness: not run - isolated micro-slice.
