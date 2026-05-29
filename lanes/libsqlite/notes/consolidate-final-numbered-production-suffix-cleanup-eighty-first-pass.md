# Consolidate Final Numbered Production Suffix Cleanup Eighty-First Pass

Consolidated the subquery covering partial planner production method family in
`SQLitePlannerSubqueryCoveringPartialCurrentSourceNextPlan` by replacing the
numbered public entry point and numbered private helpers with stable
descriptive method names. Direct callers now use
`materializeSubqueryCoveringPartialCurrentSource()`.

Observable plan metadata is preserved. The returned dependency marker
`sqlite-subquery-covering-partial-current-source-next115`, status strings,
detail text, non-overlap text, and WordPress scenario key remain unchanged as
compatibility aliases for existing evidence.

Direct test and WordPress smoke filenames were renamed to stable unsuffixed
paths:

- `SQLitePlannerSubqueryCoveringPartialCurrentSourceTest.php`
- `wordpress-subquery-covering-partial-current-source.php`

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerSubqueryCoveringPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerSubqueryCoveringPartialCurrentSourceTest.php`
- `php -l lanes/libsqlite/examples/wordpress-subquery-covering-partial-current-source.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerSubqueryCoveringPartialCurrentSourceTest.php`
  -> `1 test files, 59 assertions, 0 failures`
- `php tools/run-tests.php $(find lanes/libsqlite/tests -maxdepth 1 -type f \( -name 'SQLitePlanner*Partial*Test.php' -o -name 'SQLitePlannerStat4ExpressionPartial*Test.php' -o -name 'SQLitePlannerSubquery*Test.php' \) | sort)`
  -> `164 test files, 9632 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-subquery-covering-partial-current-source.php`
  -> emitted `subquery-covering-partial-current-source-ready`

Dependency closure: no new support component is needed; this is a production
suffix cleanup over the existing native expression-index/subquery planner
implementation.

Non-overlap: this pass only removes numbered production helper names in the
subquery covering partial planner family. It does not change JSON, pager,
B-tree, trigger, row-value, upstream-suite, WAL, or STAT4 generated-key
behavior.
