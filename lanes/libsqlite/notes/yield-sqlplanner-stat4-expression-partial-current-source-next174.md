# sqlplanner-stat4-expression-partial-current-source-next174

## Behavior

Adds a bounded current-source planner check for STAT4-backed partial expression
indexes over copied `wp_options` rows. The slice admits a prepared
`lower(option_name)` range plan when next-source row churn is outside the
searched expression range, but forces reprepare when rows inside the proven
partial-index range change, or when schema/stat4 generation changes.

This intentionally does not alter the accepted expression-index cost ranking
path. It is a lane-local planner materialization helper for the current-source
next174 slice.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext174Test.php`
  - `1 test files, 67 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-planner-stat4-expression-partial-current-source-next174.php --self-test`
  - `application-planner-stat4-expression-partial-current-source-next174 self-test passed`

## Non-Overlap

Avoids accepted next170 IN-bucket row-churn admission, next169 competing full
expression-index cost behavior, accepted expression ORDER BY, JSON, WAL, VFS,
B-tree, and upstream-runner surfaces. This slice is only STAT4 range-row
stability for partial expression indexes.

## Dependency Closure

No new support component is needed. The slice reuses bounded STAT4 expression
partial planner metadata and adds only current-source range-row invalidation
checks.
