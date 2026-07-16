# JSON Table Hidden Path Generated Current Source Next143

## Behavior

Adds `SQLiteJsonTablePlan::currentSourceHiddenPathGeneratedNext143()`, which layers generated-column checks over the existing current/next JSON table hidden `path` plus rowid point seek plan. The slice covers a Application plugin settings option where a pinned `json_tree` cursor seeks one object row by hidden `path` and rowid, then applies generated predicates such as `$.slug`, `$.priority`, and `$.enabled` to decide whether the current cursor can be reused or the next source requires a reprepare.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableHiddenPathGeneratedCurrentSourceNext143Test.php`
- Result: `1 test files, 61 assertions, 0 failures`
- PASS-line delta: `+61` focused libsqlite PASS cases.
- Application smoke: `php lanes/libsqlite/examples/application-json-table-hidden-path-generated-current-source-next143.php`

## Non-Overlap

This avoids accepted JSON table cursor/source wiring, hidden constraint extraction, visible constraint pushdown, path generated order, generated path cost, and hidden path rowid current-source slices. The new behavior is the generated predicate replan decision after a hidden `path` plus rowid seek has selected a current-source JSON table row.

## Dependency Closure

No new support component is needed. The slice reuses the existing JSON path extractor, JSONB wrapper, JSON table planner, and TestRunner harness.
