# Planner Index WHERE Current Next23

## Behavior

This slice extends covering-index planning for partial indexes whose
`CREATE INDEX ... WHERE` predicate is a range predicate. Ordinary WHERE terms
using `<`, `<=`, `>`, `>=`, `BETWEEN`, reversed constant comparisons, and
`IN (...)` lists now prove a partial index usable only when the search
constraint implies the partial predicate. AND/OR partial predicates remain
composed through the existing `SQLiteIndexPredicate` tree.

The Application path is copied `wp_options` option-name planning for partial
covering indexes such as:

```sql
CREATE INDEX idx_autoload_plugin_cover
ON wp_options(autoload, option_name, option_value)
WHERE autoload = 'yes' AND option_name >= 'plugin_'
```

## Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerIndexWhereCurrentNext23Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 50 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-planner-index-where-current-next23.php --self-test
application-planner-index-where-current-next23 self-test passed
```

## Delta

- `phpPass`: `8166 -> 8216` for the 50 verified focused PASS lines in this
  isolated worktree.
- `benchmarkDenominator.mapped`: `458 -> 459` for one newly mapped focused
  upstream-style partial-index WHERE planner evidence row.
- Root harness: not run; isolated micro-slice.

## Non-Overlap

This does not repeat accepted expression-index range-cost ranking, SQL
expression `ORDER BY`, SELECT SQL subqueries, Unicode GLOB ranges, JSON table
constraints/cursors/sources, WAL/VFS transaction application, or B-tree
freelist/page-move clusters. The new behavior is specifically ordinary
covering-index partial WHERE implication for range and IN-list constraints.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
`CREATE INDEX` parser, `SQLiteIndexPredicate`, and covering-index planner.
