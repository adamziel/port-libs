# JSON table generated path rowid cost current-source next1001-1016

This slice extends the consolidated generated-path rowid cost alias surface from next1000 through next1016 without adding a duplicate numbered implementation.

- Class surface: `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext1001()` through `Next1016()` reuse the existing next236 current-source cost selector and alias helper.
- WordPress example: `wordpress-json-table-generated-path-rowid-cost-current-source-next1001-1016.php --self-test`.
- Focused tests: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext9851000Test.php` and `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext10011016Test.php`.

Non-overlap: this continues directly from next985-1000 and only widens the current-source generated JSON path rowid cost alias range. It does not add new JSON table planner behavior, WAL/VFS, B-tree, trigger, schema, or encoding coverage.
