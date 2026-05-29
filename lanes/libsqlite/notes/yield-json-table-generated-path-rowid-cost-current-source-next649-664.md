# JSON table generated-path rowid cost next649-664

This direct follow-on extends the current-source generated-path rowid cost selection aliases from next648 through next664.

- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext649664Test.php`.
- Boundary check: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext633648Test.php` now verifies next648 hands off to next649.
- Example self-test: `wordpress-json-table-generated-path-rowid-cost-current-source-next649-664.php --self-test`.
- No new numbered source class was added because the established canonical source class already owns this JSON table generated-path rowid cost alias family.
- Scope is limited to next649-664 alias keys, dependencies, reader policies, replan reasons, and preserved current-source rowid point cost.

Dependency closure: no new support component needed; this reuses the existing current-source generated-path rowid yield guard and cost selection alias helper.
