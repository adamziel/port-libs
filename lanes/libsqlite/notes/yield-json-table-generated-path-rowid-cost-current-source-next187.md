# JSON table generated path rowid cost current-source next187

Behavior slice: adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext187()`.

- Extends accepted next184 generated-path/rowid final-cost admission with a yield-time source guard.
- A yielded current-source `json_tree()` row can continue only when the observed source generation still matches the pinned current source and the yielded rowid is present in the final-cost snapshot.
- A changed next-source generation, missing yielded rowid, non-covering snapshot, or changed final-cost fingerprint forces reprepare before more rows are yielded.

Application path: `examples/application-json-table-generated-path-rowid-cost-current-source-next187.php --self-test` models copied `wp_options` plugin-rule diagnostics where a yielded generated-path rowid cursor can continue for the current source, while a changed next-source settings JSON aborts stale cursor reuse.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext187Test.php`
- Result: `1 test files, 53 assertions, 0 failures`
- Application smoke: `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next187.php --self-test`
- Result: `application-json-table-generated-path-rowid-cost-current-source-next187 self-test passed`

Non-overlap: avoids accepted next161/163/175/180/184 generated-path rowid cost, cache, materialization, xColumn, and final-cost admission behavior. This slice only adds yield-time observed-source and yielded-rowid admission on top of the existing final-cost snapshot, and does not repeat JSON visible/hidden constraints, parser-level JSON table SELECT sources, cursor open/rewind behavior, host joins, or aggregate/window behavior.

Dependency closure: no new support component is needed; this reuses native PHP JSON table generated-path planning, rowid alias handling, xColumn final-cost snapshots, and current-source yield metadata.
