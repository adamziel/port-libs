# JSON table generated path rowid cost current-source next1017-1032

This slice extends the consolidated generated-path rowid cost alias surface from next1016 through next1032 without adding a duplicate numbered implementation.

- Class surface: `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext1017()` through `Next1032()` reuse the existing next236 current-source cost selector and alias helper.
- WordPress example: `wordpress-json-table-generated-path-rowid-cost-current-source-next1017-1032.php --self-test`.
- Focused tests: `SQLiteJsonTableGeneratedPathRowidCostSelectionTest.php` and `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext10171032Test.php`.

Non-overlap: this continues directly from next1001-1016 and only widens the current-source generated JSON path rowid cost alias range. It does not add new JSON table planner behavior, WAL/VFS, B-tree, trigger, schema, or encoding coverage.
