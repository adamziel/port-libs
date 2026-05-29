# JSON table generated-path rowid cost current-source next953-968

The next953-968 slice extends the consolidated generated-path rowid current-source cost aliases without adding a duplicate planner class.

- Planner surface: `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext953()` through `currentSourceGeneratedPathRowidCostCurrentSourceNext968()`.
- Scope: `SQLiteJsonTablePlan` alias helpers only; no new support component.
- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext953968Test.php` covers dependencies, reader policies, current/next alias keys, replan reasons, rowid cost preservation, and stable-source reuse for each alias.
- Example self-test: `wordpress-json-table-generated-path-rowid-cost-current-source-next953-968.php --self-test`.
- Continuity: next937-952 remains covered by its grouped handoff test; next968 now rejects next969 so the next slice can pick up at json969-984.
