# Optimizer WHERE Term Index Current

## Behavior

This slice makes ordinary `IS NULL` WHERE terms usable as current index terms.
SQLite can seek an index for `column IS NULL`; this is not the same as
`column = NULL`, which remains unusable because SQL equality with NULL is never
true. Covering-index and multicolumn range planning now treat `IS NULL` as an
equality-like prefix term, preserve suffix `ORDER BY` usability, and let STAT4
sample matching count NULL keys.

The Application path is copied `wp_options` cleanup/import planning for rows
whose `option_value` is intentionally SQL NULL while `option_name` remains a
covering suffix.

## Evidence

Before the implementation:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteOptimizerWhereTermIndexCurrentTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 17 assertions, 13 failures
```

After the implementation:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteOptimizerWhereTermIndexCurrentTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 28 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-optimizer-where-term-index-current.php --self-test
application-optimizer-where-term-index-current self-test passed
```

Related planner family check:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerIndexWhereCurrentNext23Test.php lanes/libsqlite/tests/SQLitePlannerMultiColumnRangeCurrentNext25Test.php lanes/libsqlite/tests/SQLitePlannerCoveringIndexJoinCurrentNext26Test.php lanes/libsqlite/tests/SQLiteOptimizerWhereTermIndexCurrentTest.php
Focused test run: 4 selected test files (root lock skipped)
...
4 test files, 220 assertions, 0 failures
```

## Non-Overlap

This does not repeat expression ORDER BY, expression-index range cost, partial
index range proof, JSON table constraints, VFS/WAL write application, B-tree
page movement, or suite-evidence metadata work. The slice is limited to
ordinary optimizer WHERE-term indexing for `IS NULL`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
CREATE INDEX parser, covering-index planner, multicolumn range planner, and
STAT4 sample comparison helpers.
