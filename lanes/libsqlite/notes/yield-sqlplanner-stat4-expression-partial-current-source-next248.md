# sqlplanner-stat4-expression-partial-current-source-next248

## Behavior

Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a current-source
planner guard for partial expression indexes backed by STAT4 samples. The slice
keeps current plan reuse only when duplicate expression-key runs in the current
partial rowset are covered by the current STAT4 sample rowids in index order.

The WordPress path is copied `wp_options` rows where plugin option names differ
only by case (`plugin_cache` / `Plugin_Cache`, `plugin_forms` /
`Plugin_Forms`). Reusing a stale partial expression-index STAT4 tape can misroute
the cursor after duplicate option rows are imported or normalized.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext248Test.php`
  - `1 test files, 73 assertions, 0 failures`
  - 73 PASS lines
- `php lanes/libsqlite/examples/wordpress-stat4-duplicate-run-current-source-next248.php`
  - emits `stat4-expression-partial-current-source-next248-ready`
  - emits duplicate keys `plugin_cache` and `plugin_forms`
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext248Test.php`
- `php -l lanes/libsqlite/examples/wordpress-stat4-duplicate-run-current-source-next248.php`

## Non-Overlap

This slice avoids accepted next245 sample-rowid anchor validation, next232/next242
histogram cardinality checks, expression `ORDER BY`, range-cost ranking, JSON,
WAL, VFS, B-tree, trigger, UTF, and suite-runner clusters. It only adds duplicate
expression-key run coverage validation for current-source STAT4 partial
expression-index samples.

## Dependency Closure

No new support component is needed. The implementation reuses the existing
lane-local planner source arrays, STAT4 sample metadata, and cursor-program
preview conventions.
