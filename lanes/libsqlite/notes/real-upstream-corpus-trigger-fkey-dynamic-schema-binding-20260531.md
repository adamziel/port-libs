# real-upstream-corpus-trigger-fkey-dynamic-schema-binding-20260531

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T020248Z-0`

Accepted base: `e1f1e0a66bff0730bf5e4118bd715c8a11c33354`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerD.test`
- `triggerD-3.1` main trigger binding must target `main.t300`, not a same-name `temp.t300`.
- `triggerD-3.2` temp trigger binding must target `temp.t300` while the main trigger remains bound to `main.t300`.
- `triggerD-4.1..4.2` attached-schema trigger definitions with qualified target names must reparse so the trigger continues to fire for the attached schema table.

Implemented behavior:

- Added `SQLiteDynamicTriggerForeignKeyPlan::triggerSchemaBindingResolution()` to model schema-qualified trigger binding for main, temp, and attached schema inserts.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicSchemaBinding20260531Test.php` with 2,130 distinct PASS cases and 2,511 assertions against dynamic schema-binding scenarios.
- The batch is non-overlapping with existing triggerD rowid-alias, triggerE variable, triggerupfrom, triggerG recursive-once, and trigger2 count_changes/self-reference coverage.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`: pass.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSchemaBinding20260531Test.php`: pass.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSchemaBinding20260531Test.php`: `1 test files, 2511 assertions, 0 failures`; 2,130 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicAliasVariableTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRowidVariableCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerUpdateFromTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSchemaBinding20260531Test.php`: `4 test files, 24522 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`: pass.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`: not run; guard file is absent in this worktree.

Dependency closure:

- No new support component is needed. The slice reuses existing lane-local PHP trigger/FK planning helpers and the hydrated upstream SQLite test corpus for source citation.

Expected dashboard movement:

- Count as focused PASS-line growth for the new test file: `+2130` PASS lines if accepted with no overlap.
- Mapped denominator remains complete at `1589 / 1589`; this is behavior/PASS-line growth, not denominator growth.
