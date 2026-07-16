# Real Upstream Corpus: triggerupfrom Schema Binding

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T074249Z-0`

Base accepted HEAD: `9c30c680e4b44fbeb2fc11612b28622bb7d8e322`

Upstream source files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerupfrom.test`
- `triggerupfrom-2.0..3.0`: attached-schema trigger `UPDATE ... FROM` resolves unqualified source table names in the trigger schema.
- `triggerupfrom-4.2..4.3`: `INSTEAD OF UPDATE` trigger on a view preserves old/new images, including hidden-column payloads, when the update is driven by `UPDATE ... FROM`.

Local movement:

- Added `SQLiteRealUpstreamTriggerFkeyDynamicTriggerUpdateFromSchemaBindingTest.php`.
- Refined `SQLiteDynamicTriggerForeignKeyPlan::triggerUpdateFromProgram()` so attached-schema after-insert cases report the upstream section and view-trigger logs expose the old/new hidden-column image from upstream `triggerupfrom.test`.
- New focused file contributes `23006` TestRunner PASS/assertion lines.
- Adjacent existing triggerupfrom dynamic file still passes after the helper refinement.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerUpdateFromSchemaBindingTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerUpdateFromTest.php`
- Result: `2 test files, 28237 assertions, 0 failures`.

Non-overlap:

- This slice avoids accepted trigger/FK late diagnostics, SET DEFAULT, trigger2 count_changes, triggerD/triggerE rowid-variable, triggerF WITHOUT ROWID replacement, fkey6 lifecycle, conflict-raise mask, fkey action-matrix, and existing triggerupfrom section 1/2/4 generic coverage.
- The new cases focus specifically on attached-trigger schema binding for unqualified `UPDATE ... FROM` source names and upstream-shaped view hidden-column log output.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP dynamic trigger/FK helper and hydrated upstream SQLite Tcl corpus.
