# JSON table generated-path rowid cost current-source next905-920

The next905-920 slice extends the consolidated generated-path rowid current-source cost aliases without adding a duplicate planner class.

- Planner surface: `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext905()` through `currentSourceGeneratedPathRowidCostCurrentSourceNext920()`.
- Example: `lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next905-920.php`.
- Focused test: `lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext905920Test.php`.
- Continuity: next889-904 remains covered by its grouped handoff test; next920 now rejects next921 so the next slice can pick up at json921-936.
