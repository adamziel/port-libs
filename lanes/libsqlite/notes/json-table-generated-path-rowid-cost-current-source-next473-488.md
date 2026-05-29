# JSON table generated-path rowid cost next473-488

This follow-on extends the current-source generated-path rowid cost selection aliases from next472 through next488.

- Reuse path: delegates to the accepted next236 implementation and aliases dependency, reader policy, selection keys, transition keys, and replan reasons to next473 through next488.
- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext473488Test.php`.
- Example self-test: `wordpress-json-table-generated-path-rowid-cost-current-source-next473-488.php --self-test`.
- Boundary handoff: the previous next457-472 focused test now hands off to next473, and this slice moves the no-later-alias assertion to next489.
- Non-overlap: this slice only opens next473-488 after merged next457-472 and keeps broad upstream suite evidence, unrelated planner rows, lane-status, progress, supervisor, and private tmux state untouched.
