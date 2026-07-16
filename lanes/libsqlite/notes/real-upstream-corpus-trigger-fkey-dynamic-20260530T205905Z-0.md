# real-upstream-corpus-trigger-fkey-dynamic-20260530T205905Z-0

Status: ready for integration.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger3.test`
- Sections: `trigger3-1.1..4.2`, `trigger3-5.1..5.2`, `trigger3-6`, and `trigger3-7.1..7.3`.

Focused PHP coverage:

- Added `SQLiteRealUpstreamTriggerFkeyDynamicRaiseActionTest.php` with 7,448 focused assertions over real upstream `trigger3.test` RAISE behavior:
  - table trigger `RAISE(ABORT)`, `RAISE(FAIL)`, `RAISE(ROLLBACK)`, and `RAISE(IGNORE)` statement boundaries;
  - `ROLLBACK` inside a transaction versus autocommit behavior;
  - `RAISE(IGNORE)` for update/delete row skipping;
  - nested trigger `RAISE(IGNORE)` resume behavior;
  - `INSTEAD OF` view trigger rollback/ignore/abort actions.
- Added generic helper methods to `SQLiteDynamicTriggerForeignKeyPlan` for these RAISE action boundaries and mutation paths.

Non-overlap:

- This does not repeat accepted `trigger2` dynamic update-of/WHEN/cascade/conflict coverage, fkey2/fkey6/fkey8 dynamic coverage, deferred restrict repair, trigger5 undo coverage, UPSERT/RETURNING trigger paths, or metadata-only runner rows.
- The new surface is specifically real upstream `trigger3.test` RAISE action semantics.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRaiseActionTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRaiseActionTest.php` passed: `1 test files, 7448 assertions, 0 failures`.
- `php tools/run-tests.php $(rg --files lanes/libsqlite/tests | rg 'SQLiteRealUpstreamTriggerFkeyDynamic|SQLiteRealUpstreamTriggerFkeyDeferredRestrictDynamic|SQLiteRealUpstreamTriggerFkeySavepointDeferredCorpus|SQLiteRealUpstreamTriggerFkeyDynamicNocaseRepair')` passed: `20 test files, 130043 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.
- Root harness: not run - isolated micro-slice.

Dependency closure:

- No new support component is needed. This reuses lane-local dynamic trigger/FK planning helpers and adds bounded RAISE action behavior.
