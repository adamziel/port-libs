# Planner STAT4 Numbered Method Consolidation Eighty-Sixth Pass

Consolidated the STAT4 expression-partial multi-value IN bucket fence helper
group in `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`:

- Renamed public `materializeNext166()` to
  `materializeMultiValueInBucketFence()`.
- Renamed the private `Next166` helper methods to descriptive
  `MultiValueInBucketFence` helper names.
- Updated the direct next166 test and example caller to use the stable
  descriptive entry point; next170 continues through the canonical production
  method and now calls the renamed implementation internally.
- Preserved observable `next166` result keys, status strings, dependency
  labels, detail text, non-overlap text, and error messages because dependent
  tests and generated evidence consume those values.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext166Test.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialRelevantRowChurnTest.php`
- `php -l lanes/libsqlite/examples/wordpress-planner-stat4-expression-partial-current-source-next166.php`
- `php -l lanes/libsqlite/examples/wordpress-planner-stat4-expression-partial-relevant-row-churn.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext166Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialRelevantRowChurnTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php`
- `php lanes/libsqlite/examples/wordpress-planner-stat4-expression-partial-current-source-next166.php --self-test`
- `php lanes/libsqlite/examples/wordpress-planner-stat4-expression-partial-relevant-row-churn.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a production
identifier consolidation over the existing native STAT4 expression-partial
multi-value IN bucket fence implementation.

Non-overlap: this changes only the next166 production method/helper identifiers
and direct callers. It avoids changing observable planner proof names,
dependency strings, action labels, status/provenance keys, generated
`next166...` receipt keys, JSON, WAL/VFS, B-tree, trigger, compound SELECT,
row-value, and suite evidence clusters.
