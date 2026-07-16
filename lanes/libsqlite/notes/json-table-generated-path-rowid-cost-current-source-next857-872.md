# JSON table generated-path rowid cost current-source next857-872

The next857-872 slice extends the consolidated generated-path rowid current-source cost aliases without adding a duplicate planner class.

- Planner surface: `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext857()` through `currentSourceGeneratedPathRowidCostCurrentSourceNext872()`.
- Shared implementation: the wrappers reuse `currentSourceGeneratedPathRowidCostCurrentSourceNext236()` and `jsonTableGeneratedPathRowidCurrentSourceCostSelectionAliasNext237252()`.
- Application example: copied `wp_options` JSON rule rows continue to pin rowid point-cost admission for the current source and reprepare the next reader when the generated path/source generation changes.
- Continuity: next841-856 remains covered by its grouped handoff test; next872 now rejects next873 so the next slice can pick up at json873-888.
