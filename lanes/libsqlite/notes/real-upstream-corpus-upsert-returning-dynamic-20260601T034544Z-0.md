# real-upstream-corpus-upsert-returning-dynamic-20260601T034544Z-0

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
- `returning1-11.11`: `DELETE FROM t1 RETURNING *` over an empty TEMP table with a `BEFORE INSERT` trigger returns no rows and leaves the table empty.
- `returning1-11.12`: after `DROP TRIGGER r1`, a normal `INSERT INTO t1 VALUES(5,30)` is materialized and selected back.

## Patch

- Added `SQLiteReturningTempTriggerPlan::emptyDeleteReturningAfterTriggerDrop()`.
- Added `SQLiteRealUpstreamReturningTempTriggerEmptyDeleteDynamicTest.php` with 1000 deterministic generic variants plus source-citation, dependency-closure, and malformed-input checks.
- Focused PASS movement: 1003 new TestRunner PASS cases and 12010 assertions in the new focused file.

## Non-overlap

This owns only the `returning1-11.11/11.12` empty TEMP-table `DELETE ... RETURNING` plus trigger-drop lifecycle branch. It avoids existing `returning1-11.1` through `11.7` temp trigger stream coverage, UPSERT conflict-arm matrices, `returning1-20` correlated DELETE subqueries, writable-schema/virtual-table returning, QRF formatting, and trigger DDL error batches.

## Dependency closure

No new support component is needed. The slice reuses the existing native temp RETURNING trigger plan and adds the missing empty-delete/drop-trigger lifecycle branch.

## Verification

- Red-first check before edit:
  `php -r 'require "lanes/libsqlite/src/SQLiteReturningTempTriggerPlan.php"; echo method_exists("PortLibs\\LibSqlite\\SQLiteReturningTempTriggerPlan", "emptyDeleteReturningAfterTriggerDrop") ? "present\n" : "missing\n";'`
  returned `missing`.
- `php -l lanes/libsqlite/src/SQLiteReturningTempTriggerPlan.php`
  passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamReturningTempTriggerEmptyDeleteDynamicTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningTempTriggerEmptyDeleteDynamicTest.php`
  passed with `1 test files, 12010 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningTempTriggerDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningTempTriggerEmptyDeleteDynamicTest.php`
  passed with `2 test files, 23014 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed with `1 test files, 4 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`
  passed with no output.
