# JSON table constraint ORDER BY cost current-source next124

## Delta

- Added `SQLiteJsonTablePlan::currentSourceConstraintOrderByCost()` for current-source to next-source JSON table planner handoff.
- The new profile records partial ORDER BY prefix coverage when visible constraints make leading ORDER terms constant, then charges only the suffix block-sort width instead of the full ORDER term width.
- Added focused PHP coverage in `SQLiteJsonTableConstraintOrderByCostTest.php`: 61 PASS lines / 61 assertions / 0 failures.
- Added WordPress smoke `wordpress-json-table-constraint-orderby-cost.php` for copied `wp_options` plugin-rule priority JSON diagnostics without `ext/sqlite`.

## Focused Evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableConstraintOrderByCostTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 61 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/wordpress-json-table-constraint-orderby-cost.php --self-test
wordpress-json-table-constraint-orderby-cost self-test passed
```

## Non-Overlap

This does not repeat accepted JSON table cursor/source wiring, hidden or visible constraint extraction, full ORDER BY consumption, indexed hidden ORDER BY, indexed constraint-cost ranking, or the next113 full-width sorter cost profile. The new behavior is the narrower planner-cost handoff where a pushed visible constraint consumes a leading ORDER BY prefix and only the remaining suffix requires a block sort.

## Dependency Closure

No new support component is needed. The implementation reuses the native JSON table planner, visible constraint coverage, JSON path validation, and bounded row-array ordering already present in the libsqlite lane.
