# real-upstream-corpus-trigger-fkey-dynamic-20260601T141607Z-0

Base accepted HEAD: `95ad41042b033e8e5ba65b976ec0413fb4eb10c1`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger7.test`
- Ported scenario: `trigger7-99.1`
- Upstream behavior: with defensive mode disabled, writable schema corrupts `sqlite_master.sql` to `nonsense`; after reopen, `DROP TRIGGER t2r5` fails with `malformed database schema ...` instead of removing the trigger.

Implementation:

- Added `SQLiteDynamicTriggerForeignKeyPlan::dropTriggerMalformedSchemaPlan()`.
- The plan validates a generic schema catalog, applies writable-schema corruption, reparses on DROP TRIGGER, reports malformed schema, preserves catalog entries, and records dependency keys for trigger7 writable-schema/drop-trigger coverage.
- Defensive mode enabled remains a write-blocked path and does not apply the corrupt catalog update.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger7MalformedSchema20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger7MalformedSchema20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger7MalformedSchema20260601Test.php`
  - `1 test files, 2758 assertions, 0 failures`
  - `2756` focused PASS cases added.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger7BatchTest.php`
  - `1 test files, 2114 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 6 assertions, 0 failures`

Status delta:

- `phpPass`: `5901085 -> 5903841` (`+2756`)
- `phpFail`: unchanged at `16`
- mapped coverage: unchanged at `1589 / 1589`

Non-overlap:

- This slice owns only `trigger7.test trigger7-99.1` malformed writable-schema DROP TRIGGER behavior.
- It avoids accepted trigger7 qualified-name diagnostics, UPDATE OF EXPLAIN pruning, selective DROP TRIGGER removal, fkey5 `foreign_key_check`, fkey6 deferred counters, fkey8 attached restrict delete, trigger9 OLD-row materialization, triggerF WITHOUT ROWID delete/replace, and triggerupfrom UPDATE FROM coverage.

Dependency closure:

- No new support component is needed. This reuses the existing lane-local dynamic trigger/FK catalog model and adds one bounded schema-reparse guard.
