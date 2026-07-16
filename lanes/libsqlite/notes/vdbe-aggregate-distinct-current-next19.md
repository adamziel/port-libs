# VDBE Aggregate DISTINCT Current Next19

Slice: `yield-sqlite-vdbe-aggregate-distinct-current-next19`

## Behavior

- Added `SQLiteVdbeAggregateDistinctCursor` for bounded VDBE-style aggregate
  DISTINCT ephemeral rowsets with `current()` / `next()` / EOF iteration.
- Applies aggregate `FILTER` before DISTINCT insertion, sorts/deduplicates with
  VDBE affinity/collation comparison, and exposes count/sum/total/avg and
  group-concat finalizers over the distinct cursor values.
- The Application smoke previews copied `wp_options` autoloaded option rows where
  duplicate byte counts are collapsed before aggregate finalization.

## Verification

```sh
php -l lanes/libsqlite/src/SQLiteVdbeAggregateDistinctCursor.php
No syntax errors detected in lanes/libsqlite/src/SQLiteVdbeAggregateDistinctCursor.php

php -l lanes/libsqlite/tests/SQLiteVdbeAggregateDistinctCurrentNext19Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteVdbeAggregateDistinctCurrentNext19Test.php

php -l lanes/libsqlite/examples/application-vdbe-aggregate-distinct-current-next.php
No syntax errors detected in lanes/libsqlite/examples/application-vdbe-aggregate-distinct-current-next.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeAggregateDistinctCurrentNext19Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 50 assertions, 0 failures

php lanes/libsqlite/examples/application-vdbe-aggregate-distinct-current-next.php --self-test
application-vdbe-aggregate-distinct-current-next self-test passed
```

`phpPass` delta: +50 focused PASS lines, from lane-local 6444 to 6494. Root
harness not run; this was an isolated micro-slice.

## Non-Overlap

This slice avoids accepted grouped SELECT SQL text, expression ORDER BY,
correlated subquery text execution, JSON table cursor/source/constraint
pushdown, Unicode GLOB, VFS file writer/lock/sync/rollback clusters, WAL
checkpoint/savepoint byte truncation, and B-tree page-move/root-collapse/
overflow-freelist clusters. It is limited to the aggregate DISTINCT ephemeral
cursor current/next behavior needed by VDBE-style aggregate execution.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded
`SQLiteVdbeSortCompare`, `SQLiteNumericAggregate`, and `SQLiteTextAggregate`
helpers.
