# sqlplanner-stat4-expression-partial-current-source relevant-row-churn

## Scope

Adds a bounded current-source planner refinement for STAT4 expression partial
indexes. The accepted next166 slice blocks a prepared plan when any next-source
row signature changes. This slice narrows that invalidation: next-source row
churn is admitted when the row changes are outside the proven partial
expression-index `IN` buckets, while changes inside the bucketed key space or
STAT4 generation changes still require reprepare.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialRelevantRowChurnTest.php`
  - `1 test files, 51 assertions, 0 failures`
  - `51` PASS lines

## Application Relevance

The example models copied `wp_options` plugin-option imports where unrelated
network/lazy/site rows can be appended after planning without invalidating a
partial expression-index scan for `lower(option_name) IN (...) AND autoload =
'yes' AND blog_id = 1`.

## Non-Overlap

This avoids accepted next166 multi-key `IN` admission, expression `ORDER BY`,
range-cost, JSON, WAL, VFS, and B-tree clusters. It only changes next-source
admission when the relevant partial expression-index row signature is stable.

## Dependency Closure

No new support component is needed. The slice reuses lane-local STAT4
expression partial planning and adds a bounded signature comparison over the
already materialized current/next row arrays.
