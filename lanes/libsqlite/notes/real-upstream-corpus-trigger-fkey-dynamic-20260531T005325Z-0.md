Real upstream corpus trigger/FK dynamic slice, 2026-05-31.

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Ported section: `fkey2-2.*`, deferred foreign-key logic across explicit transactions, top-level and nested savepoints, failed RELEASE/COMMIT, ROLLBACK TO reset, duplicate-node statement rollback, and repair-before-commit behavior.

Implementation:
- Added `SQLiteDeferredForeignKeyTransactionPlan`, a generic replay model for deferred FK transaction/savepoint state using `node` and `leaf` rows from the upstream section.
- Added `SQLiteUpstreamTriggerFkeyDynamicRealCorpusTest.php` with 570 focused assertions covering upstream operation ids, expected success/failure flags, pending deferred-check state, statement rollback flags, checkpoint rowsets, failure messages, and final clean state.

Verification:
- `php -l lanes/libsqlite/src/SQLiteDeferredForeignKeyTransactionPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUpstreamTriggerFkeyDynamicRealCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamTriggerFkeyDynamicRealCorpusTest.php` -> `1 test files, 570 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNext120Test.php lanes/libsqlite/tests/SQLiteTriggerReturningRecursiveFkCurrentSourceNext124Test.php lanes/libsqlite/tests/SQLiteUpstreamTriggerFkeyDynamicRealCorpusTest.php` -> `3 test files, 754 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite` -> pass

Non-overlap:
- Avoids accepted triggerE, fkey2 deferred graph, recursive-once, restrict-action, temp-trigger, UPSERT/RETURNING trigger, and existing DELETE RETURNING FK savepoint helpers by porting the upstream `fkey2-2.*` transaction/savepoint deferred-constraint counter behavior into a generic source helper.

Dependency closure:
- No new support component is needed. This reuses existing lane test harness and pure PHP row-array state modeling.
