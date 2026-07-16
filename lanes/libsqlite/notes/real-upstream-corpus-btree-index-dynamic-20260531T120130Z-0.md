# real-upstream-corpus-btree-index-dynamic-20260531T120130Z-0

## Source Truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereG.test`
- Ported sections: `whereG-1.1` through `whereG-1.8`, `whereG-2.1` through `whereG-2.3`, `whereG-3.1` through `whereG-3.4`, `whereG-5.1.2` through `whereG-5.1.4`, `whereG-5.2.2` through `whereG-5.2.4`, and `whereG-5.3.2` through `whereG-5.3.3`.
- Behavior cluster: B-tree/index planner effects for `unlikely()` and `likelihood()` hints over LIKE joins, invalid likelihood probabilities, commuted equality join order, indexed range scans, skip-scan selection, and high-likelihood table scans.

## Patch

- Added `SQLiteBTreeIndexDynamicCorpusPlan::whereGLikelihoodPlannerCases()` with 1200 generated cases from 19 real upstream `whereG.test` planner templates.
- Added `SQLiteRealUpstreamBtreeWhereGLikelihoodPlannerDynamicTest.php` with 1203 TestRunner PASS lines and 20094 focused assertions.
- Added a generic application self-test example for the whereG likelihood planner batch.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` - passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereGLikelihoodPlannerDynamicTest.php` - passed.
- `php -l lanes/libsqlite/examples/application-btree-whereg-likelihood-planner.php` - passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereGLikelihoodPlannerDynamicTest.php` - passed, `1 test files, 20094 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereGLikelihoodPlannerDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereJRangeCostDynamicTest.php` - passed, `3 test files, 46855 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-btree-whereg-likelihood-planner.php --self-test` - passed.
- `git diff --check -- lanes/libsqlite` - passed.

## Non-Overlap

This lane avoids the accepted whereG expression-affinity work that covered sections 7, 8, and 12, and avoids accepted whereJ range-cost, where8/where9 OR planning, indexA/index8, page-move, overflow-freelist, and root-collapse clusters.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local B-tree/index dynamic corpus planner and generic planner metadata helpers.

## Root Harness

Not run - isolated micro-slice.
