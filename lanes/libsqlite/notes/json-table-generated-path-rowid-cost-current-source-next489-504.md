# JSON table generated-path rowid cost next489-504

This follow-on extends the current-source generated-path rowid cost selection aliases from next488 through next504.

- Reuse path: delegates to the accepted next236 implementation and aliases dependency, reader policy, selection keys, transition keys, and replan reasons to next489 through next504.
- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext489504Test.php`.
- Example self-test: `wordpress-json-table-generated-path-rowid-cost-current-source-next489-504.php --self-test`.
- Boundary handoff: the previous next473-488 focused test now hands off to next489, and this slice moves the no-later-alias assertion to next505.
- Non-overlap: this slice only opens next489-504 after merged next473-488 and keeps broad upstream suite evidence, unrelated planner rows, lane-status, progress, supervisor, and private tmux state untouched.
