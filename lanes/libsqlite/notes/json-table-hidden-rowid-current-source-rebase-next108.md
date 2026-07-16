# JSON Table Hidden Rowid Current Source Rebase Next108

## Scope

This slice wires parser-level JSON table joins so hidden rowid aliases supplied by the statement `WHERE` predicate can participate in the dynamic `json_each` / `json_tree` cursor plan for the current source row. The `WHERE` predicate remains as a residual filter, preserving LEFT JOIN and duplicate-constraint semantics while letting the virtual-table cursor narrow row materialization from current-source values.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableHiddenRowidCurrentSourceRebaseNext108Test.php`
- Result: `1 test files, 51 assertions, 0 failures`
- Focused PASS-line delta: `+51`
- Application smoke: `php lanes/libsqlite/examples/application-json-table-hidden-rowid-current-source-rebase-next108.php --self-test`
- Root harness: not run - isolated micro-slice

## Non-Overlap

Avoids accepted JSON table cursor/source, hidden-constraint extraction, visible-constraint pushdown, lateral rowid, and batch104/105 rowid/hidden-current-source clusters by covering only the remaining joined-source case where the hidden rowid alias appears in `WHERE` rather than `ON`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP JSON table planner, SELECT parser, row-array executor, and Application copied `wp_options` smoke path.
