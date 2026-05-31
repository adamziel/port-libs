# Real Upstream Trigger/FK Dynamic Attached Restrict

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T031434Z-0`
- Base accepted HEAD: `d3f35d53d135e23f73a270582d60d9916715bb54`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test`
- Upstream scenarios: `fkey8.test` `6.1` through `6.3`, attached-schema `ON DELETE RESTRICT` with `main.c1` shadowing `aux.c1`.
- PHP behavior added: `SQLiteDynamicTriggerForeignKeyPlan::fkey8AttachedRestrictDeletePlan()` models attached child-table resolution, ignores same-name main child rows for attached FK enforcement, and preserves parent rows when attached `RESTRICT` blocks the delete.
- Focused test added: `SQLiteRealUpstreamTriggerFkeyDynamicAttachedRestrict20260531Test.php`, `11708` assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicAttachedRestrict20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicAttachedRestrict20260531Test.php` -> `1 test files, 11708 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses the existing trigger/FK row-array planner surface.
