# real-upstream-corpus-trigger-fkey-dynamic-20260530T203736Z-0

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T203736Z-0`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger5.test`
- Scenario: `trigger5-1.1`

Behavior added:

- Adds native PHP modeling for the `trigger5.test` AFTER DELETE undo trigger.
- The new helper emits one undo SQL statement for each deleted old row using SQLite `quote()` semantics for REAL, TEXT with embedded quotes, NULL, and integer values.
- The focused dynamic corpus varies delete predicates and old-row value shapes across 120 cases, checking source attribution, operation metadata, deletion counts, emitted undo SQL, old-row image preservation, and remaining row images.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger5UndoTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger5UndoTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger5UndoTest.php`
  - `1 test files, 1562 assertions, 0 failures`

Non-overlap:

- This slice does not repeat accepted `fkey1`, `fkey2`, `fkey3`, `fkey4`, `fkey5 foreign_key_check`, `fkey6`, `fkey7`, `fkey8`, `e_fkey`, `trigger1`, `trigger2`, `trigger3 RAISE`, `trigger4`, `trigger7`, `triggerB`, `triggerC`, RETURNING/UPSERT, PRAGMA foreign-key catalog/index-xinfo, or metadata-only runner rows.
- The new surface is specifically upstream `trigger5.test trigger5-1.1` old-row AFTER DELETE undo SQL generation and SQLite quoting behavior inside the trigger body.

Dependency closure:

- No new support component is needed. The helper reuses existing lane-local SQLite literal quoting from `SQLiteRealExpressionAffinityCorpusPlan::quote()` and the existing dynamic trigger/FK planning class.

Next task:

- Continue trigger/FK real-corpus work only with a distinct unported upstream range, such as remaining `trigger5` integrity-adjacent admission if needed, `trigger6`/`trigger8` behavior, or pivot away from trigger/FK if the remaining ranges overlap accepted batches.
