# yield-sqlite-jsonb-path-index-covering-current-next34

## Status

Added bounded expression-index planner support for JSON path expressions used
by copied `wp_options` rows. `SQLiteSelectExpressionIndexPlan` now matches
`json_extract()`, `jsonb_extract()`, `->`, and `->>` predicates by source
column plus normalized JSON path, then reuses the existing expression-index
tail metadata for covering-index and `ORDER BY` decisions.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbPathIndexCoveringCurrentNext34Test.php
Focused test run: 1 selected test files (root lock skipped)
53 PASS lines
1 test files, 118 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/application-jsonb-path-index-covering-current-next34.php --self-test
application-jsonb-path-index-covering-current-next34 self-test passed
```

## Non-Overlap

This avoids accepted parser-level JSON path operator execution, JSON table
cursor/source/visible-hidden constraint work, expression `ORDER BY`, generic
expression-index range-cost ranking, partial-index WHERE implication planning,
VFS writer/lock/sync paths, WAL checkpoint/savepoint/rollback paths, and
B-tree page move/root-collapse/overflow freelist clusters. The new behavior is
planner matching for JSONB path expression indexes with covering and current/
next trailing-column order metadata.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
`SQLiteCreateIndex`, JSON path validation, expression-index planner, and index
tail metadata.

## Next

Wire this planner choice into any future schema-backed SELECT executor path
that chooses between JSONB path expression indexes and table scans.
