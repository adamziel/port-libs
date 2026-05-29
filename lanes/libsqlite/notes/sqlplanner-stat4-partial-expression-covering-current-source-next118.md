# sqlplanner-stat4-partial-expression-covering-current-source-next118

This slice adds `SQLitePlannerStat4PartialExpressionCoveringCurrentSourceNextPlan`, an additive current-source wrapper for stale prepared statements that can use a partial expression index as a covering STAT4 cursor after schema/stat4/index metadata changes.

Behavior covered:

- selects the current index source when schema cookie, STAT4 generation, or index signature changes;
- requires a proved partial predicate, STAT4 matched samples, and covering payload columns before deferring the table seek;
- materializes current/next covering rows from a `lower(option_name)` partial expression index over copied `wp_options` rows;
- preserves fallback evidence for missing partial proof, missing STAT4 samples, non-covering indexes, and invalid source metadata.

Focused verification:

```text
php -l lanes/libsqlite/src/SQLitePlannerStat4PartialExpressionCoveringCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePlannerStat4PartialExpressionCoveringCurrentSourceNext118Test.php
php -l lanes/libsqlite/examples/wordpress-stat4-partial-expression-covering-current-source-next118.php

php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4PartialExpressionCoveringCurrentSourceNext118Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 64 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-stat4-partial-expression-covering-current-source-next118.php
wordpress stat4 partial expression covering current-source next118: source=current index=idx_wp_options_lower_plugin_covering_next118 rows=3 names=plugin_cache,Plugin_Forms,plugin_seo
```

Dashboard delta: `phpPass` +64 focused PASS lines (`45302 -> 45366`). Mapped upstream coverage remains `604 / 1589`; this patch does not claim a fresh upstream-runner inventory row.

Non-overlap: avoids accepted expression `ORDER BY`, expression-index range-cost ranking, next114 partial-collation STAT4, next115 subquery partial covering, JSON table, WAL/VFS, and B-tree clusters. The new behavior is current-source STAT4 partial expression covering payload selection.

Dependency closure: no new support component is needed. The patch reuses native expression-index parsing, partial-predicate proof, STAT4 sample diagnostics, and lane-local planner row-stream evidence.
