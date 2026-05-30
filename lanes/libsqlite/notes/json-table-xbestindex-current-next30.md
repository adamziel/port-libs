# JSON Table xBestIndex Current/Next 30

## Delta

- Added `SQLiteJsonTablePlan::xBestIndexPlan()` for bounded SQLite virtual-table planner metadata.
- The plan records original constraint indexes, hidden `json`/`root` argv placement, visible pushdown vs residual classification, `idxNum`/`idxStr`, rowid/id `ORDER BY` consumption, estimates, and current/next constraint pairs.
- Added focused PHP coverage in `SQLiteJsonTableConstraintXBestIndexCurrentNext30Test.php`: 63 PASS lines / 63 assertions / 0 failures.
- Added Application smoke `application-json-table-xbestindex-current-next.php` for copied `wp_options` JSON diagnostics without `ext/sqlite`.

## Focused Evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableConstraintXBestIndexCurrentNext30Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 63 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-json-table-xbestindex-current-next.php
{
    "scenario": "application-json-table-xbestindex-current-next",
    "idxNum": 15,
    "idxStr": "hidden:json:=|hidden:root:=|visible:type:=|visible:atom:BETWEEN|hidden:limit:=",
    "orderByConsumed": true,
    ...
}
```

## Non-Overlap

This slice does not repeat accepted JSON table cursor iteration, parser-level JSON table SELECT `FROM`/`JOIN` source wiring, hidden `json`/`root` extraction from SQL text, visible-column constraint pushdown execution, JSON host joins, JSON derived indexes, or JSON malformed planner diagnostics. It adds the xBestIndex-style constraint usage/current-next metadata surface needed by planner/cursor integration.

## Dependency Closure

No new support component is needed. The implementation reuses existing native PHP JSON table planning, JSON path validation, JSONB/text validation, and rowid ordering semantics.
