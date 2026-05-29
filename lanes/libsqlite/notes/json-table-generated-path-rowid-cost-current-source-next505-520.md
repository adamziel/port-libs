# JSON table generated-path rowid cost next505-520

This follow-on extends the current-source generated-path rowid cost selection aliases from next504 through next520.

- Reuse path: delegates to the accepted next236 implementation and aliases dependency, reader policy, selection keys, transition keys, and replan reasons to next505 through next520.
- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext505520Test.php`.
- Example self-test: `wordpress-json-table-generated-path-rowid-cost-current-source-next505-520.php --self-test`.
- Boundary handoff: the previous next489-504 focused test now hands off to next505, and this slice moves the no-later-alias assertion to next521.
- Non-overlap: this slice only opens next505-520 after merged next489-504 and keeps broad upstream suite evidence, unrelated planner rows, lane-status, progress, supervisor, and private tmux state untouched.
