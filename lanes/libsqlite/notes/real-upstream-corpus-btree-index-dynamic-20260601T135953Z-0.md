# real-upstream-corpus-btree-index-dynamic-20260601T135953Z-0

Status: ready after focused verification.

Base accepted HEAD: `bb0d0539e37bd38885b4e91058393f13bda6b370`.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wherelimit2.test`
- Upstream sections covered: `wherelimit2-1.1` through `wherelimit2-1.4`, `wherelimit2-2.1.1` through `wherelimit2-2.2.2`, `wherelimit2-4.1`, `wherelimit2-4.3`, `wherelimit2-4.5`, `wherelimit2-5.1`, `wherelimit2-5.2`, `wherelimit2-5.4`, `wherelimit2-5.5`, `wherelimit2-6.1` through `wherelimit2-6.2`, and `wherelimit2-7.1` through `wherelimit2-7.2`.

Implemented coverage:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::whereLimit2UpdateDeleteIndexCases(1000)`.
- Added `SQLiteRealUpstreamBtreeWhereLimit2ViewIndexDynamicTest.php`.
- Covers UPDATE/DELETE `ORDER BY ... LIMIT` selection through INSTEAD OF view triggers, WITHOUT ROWID composite and integer primary-key b-trees, forced `INDEXED BY` secondary-index choices, quoted table/view/index names, materialized CTE target shadowing, and window-order DELETE target resolution.
- Adds 1003 focused TestRunner PASS cases from real upstream `wherelimit2.test` behavior.

Non-overlap:

- This owns upstream `wherelimit2.test` view/index/WITHOUT ROWID LIMIT behavior only.
- It avoids accepted `wherelimit.test` update/delete LIMIT parity, `wherelimit3.test` range-cost LIMIT planning, `where.test` section-1, `where2` through `whereN`, `index*`, `indexedby`, `bestindex*`, B-tree page relocation/root-collapse/overflow/freelist release, VFS writer/sync/lock, rollback-commit, JSON, PRAGMA, trigger/FK, row-value, SELECT expression ORDER BY/GROUP/subquery, and source-neutral cleanup clusters.

Dependency closure:

- No new support component is needed.
- Reuses the lane-local B-tree/index dynamic corpus plan infrastructure for selected-row, trigger-log, WITHOUT ROWID key, forced-index, quoted-name, and CTE target-shadowing evidence.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereLimit2ViewIndexDynamicTest.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php` -> no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` -> `lane-status json ok`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereLimit2ViewIndexDynamicTest.php` -> 1 test file, 26302 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereLimit2ViewIndexDynamicTest.php` -> 2 test files, 91472 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> 1 test file, 6 assertions, 0 failures.
- `git diff --check -- lanes/libsqlite` -> passed.

Root harness: not run - isolated micro-slice.
