# Planner Skipscan Partial Current Next28

This slice adds bounded current/next skip-scan planning for partial composite
indexes. `SQLiteIndexSkipScanPlan::betweenPartialRows()` now refuses a partial
index unless the query terms imply the partial-index `WHERE` predicate, filters
the materialized index image to rows that satisfy that predicate, and then runs
the existing skip-scan current/next loops over the remaining leading-column
prefixes.

The Application path is copied `wp_options` planning for an index shaped like:

```sql
CREATE INDEX idx_wp_options_autoload_plugin_name
ON wp_options(autoload, option_name)
WHERE kind = 'plugin' AND option_name >= 'plugin_'
```

This lets recovery/import tooling scan plugin option-name ranges through the
`autoload` leading column only when the query proves the partial predicate. It
also keeps unsafe broad queries on the table path instead of using an incomplete
partial index image.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerSkipScanPartialCurrentNext28Test.php
Focused test run: 1 selected test files (root lock skipped)
54 PASS lines / 54 assertions / 0 failures

php lanes/libsqlite/examples/application-planner-skipscan-partial-current-next28.php --self-test
application-planner-skipscan-partial-current-next28 self-test passed
```

Dependency closure: no new support component is needed. This reuses the
lane-local `SQLiteIndexPredicate` implication model and the existing
`SQLiteIndexSkipScanPlan` current/next row materializer.

Non-overlap: this avoids accepted expression-index range-cost ranking, partial
WHERE proof-only covering-index planning, SQL expression `ORDER BY`, SELECT SQL
subqueries, Unicode GLOB ranges, JSON table constraints/cursors, B-tree
overflow/root/page-move work, and accepted WAL/VFS writer or rollback clusters.
The new behavior is specifically partial-index admission and row-image
filtering for skip-scan current/next loops.
