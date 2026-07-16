# real-upstream-corpus-trigger-fkey-dynamic-20260601T084853Z-0

Status: ready for integration on accepted base `6c5f68290192c5bf57e0f3c2cca80b604bf38511`.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test`
- `triggerC-2.2`: recursive `INSERT INTO t22 SELECT x + (SELECT max(x) FROM t22) FROM t22` stops when a BEFORE INSERT trigger raises `IGNORE` at `$SQLITE_MAX_TRIGGER_DEPTH / 2` rows.
- `triggerC-2.3`: recursive `INSERT INTO t23 VALUES(new.x + 1)` over a primary-key table stops when the BEFORE INSERT trigger raises `IGNORE` above `$SQLITE_MAX_TRIGGER_DEPTH / 2`.

Implementation:

- Added `SQLiteDynamicTriggerForeignKeyPlan::triggerCRecursiveInsertDepthCutoffPlan()` for both upstream shapes.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicTriggerCInsertDepthCutoff20260601Test.php` with source-citation checks, canonical 1000-depth checks, dynamic depth/seed variants, and guard assertions.
- Updated `lane-status.json` by the verified `+12203` focused PASS-line delta, from `5766885` to `5779088`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCInsertDepthCutoff20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCInsertDepthCutoff20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCInsertDepthCutoff20260601Test.php`
  - `1 test files, 12212 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCInsertDepthCutoff20260601Test.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRecursiveOnce20260531Test.php lanes/libsqlite/tests/SQLiteUpstreamTriggerFkeyDynamicRealCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCRecursionLimit20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCBasicLifecycle20260531Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `6 test files, 47420 assertions, 0 failures`
- `php -r '$data=json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status.json valid\n";'`
  - `lane-status.json valid`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Root harness: not run - isolated micro-slice.

Dependency closure: no new support component needed; this reuses the existing dynamic trigger/FK plan surface and upstream corpus cache.

Non-overlap: this does not repeat `triggerC-2.1` recursive insert ordering, `triggerC-3` recursion-error cases, accepted triggerC indexed delete cascade, active scan trigger drop, e_fkey create-table validation, or e_fkey action-satisfaction coverage.
