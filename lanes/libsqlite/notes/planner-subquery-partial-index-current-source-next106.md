# planner-subquery-partial-index-current-source-next106

## Behavior

Adds a bounded current-source planner for `IN (SELECT ...)` subquery result
sets proving partial expression indexes. The planner materializes a supplied
single-column subquery rowset, removes duplicate non-NULL values, blocks partial
index use when SQL NULL appears, reparses stale prepared/current index sources,
and emits a covering index cursor tape for Application `wp_options` plugin-name
imports.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerSubqueryPartialIndexCurrentSourceNext106Test.php`
  - `1 test files, 64 assertions, 0 failures`
- Application smoke:
  - `php lanes/libsqlite/examples/application-planner-subquery-partial-index-current-source-next106.php --self-test`

## Non-Overlap

This avoids accepted scalar subquery execution, parser-level subquery filters,
SQL expression `ORDER BY`, STAT4 range-cost ranking, expression covering ORDER,
and batch102/103 expression-index covering ORDER planning. The new surface is
partial-index eligibility from bounded subquery-produced `IN` values on the
current source.

## Dependency Closure

No new support component is needed. The slice composes existing native
expression-index parsing and partial predicate implication with lane-local
bounded subquery row materialization.
