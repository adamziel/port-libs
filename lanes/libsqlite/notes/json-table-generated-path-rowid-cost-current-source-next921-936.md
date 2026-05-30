# JSON table generated-path rowid cost current-source next921-936

The next921-936 slice extends the consolidated generated-path rowid current-source cost aliases without adding a duplicate planner class.

- Planner surface: `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext921()` through `currentSourceGeneratedPathRowidCostCurrentSourceNext936()`.
- Shared implementation: the wrappers reuse `currentSourceGeneratedPathRowidCostCurrentSourceNext236()` and `jsonTableGeneratedPathRowidCurrentSourceCostSelectionAliasNext237252()`.
- Application example: copied `wp_options` JSON rule rows continue to pin rowid point-cost admission for the current source and reprepare the next reader when the generated path/source generation changes.
- Continuity: next905-920 remains covered by its grouped handoff test; next936 now rejects next937 so the next slice can pick up at json937-952.
