# JSON Table Generated Path Rowid Cost Current Source Next163

Behavior slice: now uses the consolidated `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostBestIndex()` entry point.

This composes the accepted generated-path rowid source-cost layer (`next160`) into xBestIndex-style cursor metadata for current-source `json_tree()` / `json_each()` planning. The profile records `idxNum`, `idxStr`, normalized rowid alias spelling, omitted path/rowid constraints, argv binding columns, consumed path/rowid ordering, estimated rows/cost, cursor admission, covering status, fingerprint transitions, and next-source replan reasons.

WordPress path: `examples/wordpress-json-table-generated-path-rowid-cost-current-source-next163.php --self-test` models copied `wp_options` plugin-rule JSON where a generated path plus `_rowid_` point constraint admits a pinned current cursor, while the shifted next source prepares a fresh cursor.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext163Test.php
# 1 test files, 57 assertions, 0 failures
```

Dependency closure: no new support component needed; this reuses native JSON table current-source, generated-path rowid-cost, JSON path validation, JSON1/JSONB, and planner metadata helpers.

Non-overlap: avoids accepted JSON table SELECT source/cursor wiring, visible/hidden constraint pushdown, generated-path rowid-cost `next145`, current-source/source-cost `next158`/`next160`, seek-cost `next159`, hidden/generated rowid layers, aggregate/window behavior, and non-JSON storage/VFS surfaces. This slice only adds the best-index/cursor-admission metadata above the existing source-cost profile.
