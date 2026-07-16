# SQL Planner STAT4 Expression Partial Current Source Next172

## Behavior

Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a bounded
native PHP planner helper for stale prepared statements that must re-read
current-source `sqlite_stat4` samples for a partial expression index.

The slice models a Application `wp_options` plugin-option scan using
`lower(option_name)` with a partial predicate (`autoload = 'yes' OR blog_id = 0`).
It refreshes schema/stat4/source fences, proves the query predicate implies one
partial-index arm, filters current rows by expression range, and emits cursor
tape evidence for the current index.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext172Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 63 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-planner-stat4-expression-partial-current-source-next172.php --self-test
```

Result:

```text
application-planner-stat4-expression-partial-current-source-next172 self-test passed
```

## Non-Overlap

This avoids accepted STAT4 collation next114, partial expression materialization
next121, STAT4 covering/skip-scan next142/147, expression-index range-cost
ranking, expression ORDER BY, and JSON generated-index planner surfaces. The
new coverage is limited to stale STAT4 expression partial-index selectivity
refresh from the current source.

## Dependency Closure

No new support component is needed. The slice reuses native PHP expression
evaluation, partial predicate implication, structured STAT4 sample parsing, and
existing lane test/example infrastructure.
