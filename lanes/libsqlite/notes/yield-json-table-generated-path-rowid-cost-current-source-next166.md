# JSON table generated path rowid cost current-source next166

Behavior slice: now uses the consolidated `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidYieldPlan()` entry point.

The new profile sits above the accepted next163 generated-path/rowid xBestIndex planner and records whether a current `json_tree()` / `json_each()` cursor can keep yielding a generated-path rowid result set through `xFilter` while a changed next source is prepared separately. It tracks argv bindings, omitted/residual constraints, rowset fingerprints, current-source pinning, covering/order consumption, and yield cost class.

WordPress path: `examples/wordpress-json-table-generated-path-rowid-cost-current-source-next166.php --self-test` models copied `wp_options` plugin-rule JSON where `path = generated_path` plus `_rowid_ = ?` admits a covering point cursor for the current source, while a shifted next JSON source has an empty current rowset and requires a fresh prepared cursor.

Non-overlap: avoids accepted next161/next163 cursor/source/best-index behavior by adding a separate yield-admission profile and tests. It does not repeat JSON visible/hidden constraint extraction, JSON table SELECT source wiring, JSON table cursor behavior, JSON aggregate/window work, or malformed JSONB planner diagnostics.

Dependency closure: no new support component is needed; this reuses the lane-local JSON parser/path, JSON table planner, rowid alias constraint, xBestIndex, and current-source profile helpers.

Focused verification:

```text
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext166Test.php
php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next166.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext166Test.php
php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next166.php --self-test
git diff --check -- lanes/libsqlite
```
