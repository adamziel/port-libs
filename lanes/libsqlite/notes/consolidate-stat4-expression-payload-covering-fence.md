# STAT4 Expression Payload Covering Fence Consolidation

Slice: `consolidate-final-numbered-production-suffix-cleanup-dynamic-20260530T025336Z-0`

Changed the remaining STAT4 expression-payload covering production entry point and its private helpers from `Next218`-suffixed names to descriptive names in the canonical `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` class. The returned status strings, dependency markers, proof keys, and payload signatures are preserved for existing evidence compatibility.

Direct caller cleanup:

- The direct planner test now uses the descriptive expression-payload covering fence filename.
- The Application smoke now uses the descriptive expression-payload covering fence filename.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php` - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialExpressionPayloadCoveringFenceTest.php` - no syntax errors.
- `php -l lanes/libsqlite/examples/application-planner-stat4-expression-payload-covering-fence.php` - no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialExpressionPayloadCoveringFenceTest.php` - 1 test file, 70 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*.php` - 133 test files, 7547 assertions, 0 failures.
- `php lanes/libsqlite/examples/application-planner-stat4-expression-payload-covering-fence.php` - emitted ready payload-covering summary.
- `git diff --check -- lanes/libsqlite` - clean.

Dependency closure: no new support component needed; this is a naming consolidation over the existing STAT4 expression partial planner evidence.

Non-overlap: limited to the STAT4 expression-payload covering fence helper/caller names. It avoids JSON, WAL, VFS, B-tree, trigger, UTF, release-runner, and other STAT4 numbered helper clusters.
