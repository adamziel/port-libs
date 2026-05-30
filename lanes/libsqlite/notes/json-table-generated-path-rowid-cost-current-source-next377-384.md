# JSON table generated-path rowid cost next377-384

This follow-on extends the current-source generated-path rowid cost selection aliases from next376 through next384.

- Scope: `SQLiteJsonTablePlan` alias helpers only; no new support component.
- Reuse path: delegates to the accepted next236 implementation and aliases dependency, reader policy, selection keys, transition keys, and replan reasons to next377 through next384.
- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext377384Test.php`.
- Example self-test: `application-json-table-generated-path-rowid-cost-current-source-next377-384.php --self-test`.
- Non-overlap: this slice only opens next377-384 after merged next369-376 and keeps broad upstream suite evidence, unrelated planner rows, and private tmux state untouched.
