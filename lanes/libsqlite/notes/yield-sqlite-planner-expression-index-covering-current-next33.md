# yield-sqlite-planner-expression-index-covering-current-next33

## Status

Added expression-output covering metadata to `SQLiteSelectExpressionIndexPlan`.
Expression indexes now report covering status when the requested output is the
indexed expression itself, such as `lower(option_name)`, `upper(option_name)`,
`length(option_name)`, or `CAST(option_value AS INTEGER)`, while still refusing
to treat the source column as covered unless it is present in trailing columns
or explicit covering metadata.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerExpressionIndexCoveringCurrentNext33Test.php
Focused test run: 1 selected test files (root lock skipped)
40 PASS lines
1 test files, 115 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/application-select-expression-index-covering.php
```

The smoke reports `idx_wp_options_lower_name_covering` selected with
`covering=true`, `coveringExpressions=["lower(option_name)"]`, and trailing
columns `autoload` and `option_value`.

## Non-Overlap

Avoids accepted expression covering-order planning, partial-index WHERE
implication, expression-index range-cost ranking, SQL expression `ORDER BY`,
parser-level SELECT/JOIN/GROUP BY/subquery execution, JSON table cursor/source
work, VFS writer/lock/sync/rollback work, WAL byte/checkpoint transactions,
B-tree page move/root-collapse/interior-merge/overflow freelist clusters, and
Unicode GLOB ranges.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
`SQLiteCreateIndex`, `SQLiteIndexColumn`, and `SQLiteSelectExpressionIndexPlan`
metadata and extends only planner result metadata.

## Next

Wire expression-output covering decisions into broader SELECT executor index
scan selection once schema-backed row decoding can choose between table reads
and expression-index reads.
