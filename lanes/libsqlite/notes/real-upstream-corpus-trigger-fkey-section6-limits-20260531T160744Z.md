# Real Upstream Trigger/FK Section 6 Limits

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T160744Z-0`
- Base accepted HEAD: `babccb1e8657d71e59b3c627c9000c66f8705d7f`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test`
- Upstream sections: `e_fkey-62` MATCH clauses and unsupported `SET CONSTRAINTS`; `e_fkey-63` FK actions governed by trigger recursion depth limits.

## Behavior Added

- `SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyMatchSimplePlan()` models SQLite parsing `MATCH SIMPLE`, `MATCH PARTIAL`, and `MATCH FULL`, while enforcing all as MATCH SIMPLE with NULL child-key short-circuit behavior.
- `SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyConstraintTimingPlan()` models unsupported `SET CONSTRAINTS` syntax and the fixed-at-create-time immediate/deferred constraint boundary.
- `SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyActionDepthLimitPlan()` models FK cascade update/delete actions as trigger programs for depth-limit purposes while preserving the recursive-trigger pragma non-effect on FK actions.

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSection6Limits20260531Test.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSection6Limits20260531Test.php` -> `1 test files, 36617 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSection6Limits20260531Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `2 test files, 36620 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` -> `lane-status json ok`.
- `git diff --check -- lanes/libsqlite` -> no whitespace errors.

## Non-Overlap

This covers `e_fkey.test` section 6 limits and unsupported-feature behavior. It avoids the already accepted or existing clusters for `e_fkey-64` recursive-trigger cascade behavior, `triggerC` ordinary trigger recursion, `fkey2` conflict policy, `fkey5` foreign-key checks, required-index diagnostics, and implicit `DROP TABLE` FK actions.

## Dependency Closure

No new support component is needed. The slice reuses the existing libsqlite trigger/FK dynamic corpus model and hydrated upstream source cache only.
