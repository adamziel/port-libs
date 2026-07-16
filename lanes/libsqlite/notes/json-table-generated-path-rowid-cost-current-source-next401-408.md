# JSON table generated-path rowid cost next401-408

This follow-on extends the current-source generated-path rowid cost selection aliases from next400 through next408.

- Reuse path: delegates to the accepted next236 implementation and aliases dependency, reader policy, selection keys, transition keys, and replan reasons to next401 through next408.
- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext401408Test.php`.
- Example self-test: `application-json-table-generated-path-rowid-cost-current-source-next401-408.php --self-test`.
- Non-overlap: this slice only opens next401-408 after merged next393-400 and keeps broad upstream suite evidence, unrelated planner rows, and private tmux state untouched.
