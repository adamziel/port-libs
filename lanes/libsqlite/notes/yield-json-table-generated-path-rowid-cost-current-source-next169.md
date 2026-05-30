# yield-json-table-generated-path-rowid-cost-current-source-next169

Status: focused PHP behavior growth for current-source JSON table generated-path plus rowid cost planning.

Behavior:
- Added `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext169()`.
- The planner composes the accepted generated-path rowid cost profile into xFilter/yield metadata: admission decision, yield mode, ordered rowid tape, argv program, omitted/residual columns, estimated rows/cost, program fingerprint, and current/next replan reasons.
- The current source remains pinned only when the generated path and rowid seek produce a concrete yield tape; shifted next-source rows prepare a fresh JSON table yield plan.

Application path:
- `examples/application-json-table-generated-path-rowid-cost-current-source-next169.php --self-test` models copied `wp_options` plugin-rule JSON where a generated path plus `_rowid_` point constraint can yield from the current `json_tree()` cursor while the next copied source reparses.

Verification:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext169Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 1 test files, 59 assertions, 0 failures
```

Dependency closure: no new support component needed; this reuses native JSON table current-source, generated-path rowid-cost, seek, path validation, and planner metadata helpers.

Non-overlap: avoids accepted JSON table SELECT source/cursor wiring, visible/hidden constraint pushdown, generated-path rowid-cost `next145`, source-cost `next160`/`next162`, best-index metadata `next163`, cost profile `next165`, path/order layers, aggregate/window behavior, and non-JSON storage/VFS surfaces. This slice only adds the xFilter/yield program metadata above the existing cost profile.
