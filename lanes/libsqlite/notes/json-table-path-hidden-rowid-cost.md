# JSON table path hidden rowid cost current-source next126

## Delta

- Added `SQLiteJsonTablePlan::currentSourcePathHiddenRowidCost()` for current-source to next-source JSON table planner handoff.
- The new profile records composite path + hidden rowid alias lookup signatures, rowid-normalized cost, scan strategy, rowid/path tapes, and replan reasons.
- Added focused PHP coverage in `SQLiteJsonTablePathHiddenRowidCostTest.php`: 56 PASS lines / 56 assertions / 0 failures.
- Added Application smoke `application-json-table-path-hidden-rowid-cost.php` for copied `wp_options` plugin-rule JSON diagnostics without `ext/sqlite`.

## Focused Evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTablePathHiddenRowidCostTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 56 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-json-table-path-hidden-rowid-cost.php --self-test
application-json-table-path-hidden-rowid-cost self-test passed
```

## Non-Overlap

This does not repeat accepted JSON table cursor/source wiring, path-only constraint pushdown, hidden rowid SQL filtering, visible constraint pushdown, hidden ORDER BY, or partial ORDER BY cost. The new behavior is the narrower planner-cost handoff where path constraints and rowid/oid/_rowid_ constraints intersect into one cost profile for current-source cursor reuse.

## Dependency Closure

No new support component is needed. The implementation reuses native JSON table path pushdown, hidden rowid alias normalization, indexed-cost planning, and row-array current-source evidence already present in the libsqlite lane.
