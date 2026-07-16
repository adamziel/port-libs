# SELECT HAVING/GROUP Aggregate Corpus Next7

Micro-slice: `yield-sqlite-select-having-group-aggregate-corpus-next7`

## Behavior

Adds a lane-scoped upstream-style SELECT SQL corpus for grouped aggregate HAVING
semantics over copied `wp_options` rows. The cases cover aggregate comparison
predicates, NULL groups, composite group keys, `AND`/`OR`, `BETWEEN`, `IN`,
`LIKE`/`GLOB` group predicates, aggregate arithmetic in HAVING, DISTINCT after
grouping, joined metadata rows, VALUES CTE feeds, bind parameters, and final
ORDER BY/LIMIT/OFFSET after HAVING.

## Evidence

- `php -l lanes/libsqlite/tests/SQLiteSelectHavingGroupAggregateNext7Test.php`
- `php -l lanes/libsqlite/examples/application-select-having-group-aggregate-next7.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectHavingGroupAggregateNext7Test.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 50 assertions, 0 failures`
  - 50 PASS lines
- `php lanes/libsqlite/examples/application-select-having-group-aggregate-next7.php`
  emits copied `wp_options` grouped HAVING preview JSON without requiring
  `ext/sqlite`.

## Dashboard Delta

`lane-status.json` `phpPass` moves from `2017` to `2067`, exactly matching the
50 newly verified PASS lines in the new focused test file. No mapped upstream
denominator change is claimed.

## Non-Overlap

This is a focused corpus-growth handoff. It avoids changing parser/executor
helpers already identified as accepted or queued in the supervisor prompt,
including parser-level GROUP BY/HAVING implementation, composite GROUP BY
execution, expression ORDER BY, subqueries, JSON table source/cursor work,
VFS file writer/locking/sync, WAL savepoint byte truncation, rollback-journal
apply/commit, B-tree page relocation/root collapse/interior merge, overflow
freelist release, Unicode GLOB ranges, and JSON visible/hidden constraint
pushdown.

## Dependency Closure

No new support component is needed. The corpus reuses existing native PHP
`SQLiteSelectSql` and row-array execution; the Application smoke runs without
`ext/sqlite` or live services.
