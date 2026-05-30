# SQL Planner STAT4 Partial Index Current Source Next88

## Behavior

- Extended `SQLiteMultiColumnRangePlan` so partial multicolumn indexes can use
  `stat4Samples` for the current range source after equality-prefix filtering.
- Collapses same-column lower/upper range constraints into one bounded current
  source before estimating rows, while preserving unbounded range fallback when
  no STAT4 evidence exists.
- Exposes STAT4 current/next boundary evidence, matched sample count, current
  source column, and source offset through `SQLitePartialIndexOrderCurrentSourcePlan`.

## Application Relevance

Copied multisite `wp_options` plugin-option scans often use partial indexes
such as `WHERE kind = 'plugin' AND option_name >= 'plugin_'`. This slice lets a
native PHP planner preview choose and size the proved partial index using
`sqlite_stat4` samples for the active `option_name` range before an import or
diagnostic query runs without ext/sqlite.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4PartialIndexCurrentSourceNext88Test.php`
  - `1 test files, 44 assertions, 0 failures`
  - 44 focused PASS lines
- `php lanes/libsqlite/examples/application-planner-stat4-partial-index-current-source-next88.php --self-test`
  - `application-planner-stat4-partial-index-current-source-next88 self-test passed`

## Non-Overlap

Avoids accepted STAT4 expression range current-source coverage, partial-index
ORDER BY current-source coverage, STAT4 JSON covering order coverage, expression
index range-cost ranking, and the accepted SQL expression `ORDER BY` planner
surface. This patch is limited to multicolumn partial-index STAT4 estimates at
the active range-column source.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
`SQLiteCreateIndex`, `SQLiteIndexPredicate`, and planner data structures.
