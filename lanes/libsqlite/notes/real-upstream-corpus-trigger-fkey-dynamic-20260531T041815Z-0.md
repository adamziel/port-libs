# real-upstream-corpus-trigger-fkey-dynamic-20260531T041815Z-0

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T041815Z-0`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey3.test`
- Ported section: `fkey3-2.1` parent primary-key update with `ON UPDATE SET NULL`.

Behavior added:

- `SQLiteDynamicTriggerForeignKeyPlan::parentUpdateForeignKeyAction()` models a parent key update and applies child-side FK actions for `SET NULL`, `CASCADE`, and statement-time `NO ACTION` validation.
- The focused test adds dynamic generic application rows over the upstream `fkey3-2.1` shape and checks child-key rewrites, parent-key replacement, orphan detection, action diagnostics, and unsupported-action rejection.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicParentUpdateActionTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicParentUpdateActionTest.php`
- `git diff --check -- lanes/libsqlite`

Expected movement:

- Focused test adds 1723 assertions in 1 new TestRunner file.
- `benchmarkDenominator.mapped` remains `1589 / 1589`; this is PHP behavior PASS-line growth against already mapped upstream `fkey3.test`.

Dependency closure:

- No new support component is needed. The slice reuses the existing dynamic trigger/FK planner and hydrated upstream SQLite test cache.

Non-overlap:

- This does not repeat existing fkey3 self-referential insert, composite update failure, fkey2 action matrix, fkey6 deferred restrict, trigger9 view-rowid, triggerD/E, or quoted cascade slices. The new surface is specifically the upstream `fkey3-2.1` parent update `ON UPDATE SET NULL` action path plus adjacent action validation in the same generic planner.
