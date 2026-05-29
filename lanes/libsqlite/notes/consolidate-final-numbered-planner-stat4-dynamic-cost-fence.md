# Planner STAT4 Dynamic Cost Fence Consolidation

Consolidated the STAT4 expression-partial dynamic cost-fence production entrypoint from `materializeNext169()` and private `*Next169()` helpers into the stable `materializeStat4PartialCostFence()` implementation on `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`.

The direct test and WordPress smoke were renamed to unsuffixed cost-fence files. Existing observable proof metadata, status strings, dependency markers, and `next169` payload keys are preserved because downstream consolidation tests still assert those accepted evidence keys.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceCostFenceTest.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-cost-fence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceCostFenceTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-cost-fence.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this is a production helper-name consolidation over the existing lane-local STAT4 expression partial planner.
