# JSON Table Generated Path Rowid Cost Current Source Next180

## Scope

This slice adds `SQLiteJsonTablePlan::generatedPathRowidMaterializationPlan()`, a bounded current-source materialization step after the accepted generated-path/rowid xFilter program. It turns a pinned `json_tree` generated-path plus rowid xFilter program into seek tape and materialized row output, while changed next-source rows are held behind a reset/reprepare policy.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidMaterializationPlanTest.php`
- Result: `1 test files, 66 assertions, 0 failures`
- Application smoke: `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-materialization.php --self-test`
- Result: `application-json-table-generated-path-rowid-materialization self-test passed`

## Non-Overlap

Avoids accepted JSON table cursor/source/hidden/visible constraint work and the prior next145-next177 generated-path/rowid cost/xBestIndex/xFilter profiles. This slice starts after next177 and only adds the current-source row materialization handoff and stale next-source reprepare classification.

## Dependency Closure

No new support component is needed. The patch reuses the existing native JSON path, JSON table planner, rowid alias, xBestIndex, and xFilter profiles.
