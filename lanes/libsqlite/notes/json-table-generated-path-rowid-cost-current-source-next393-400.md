# JSON table generated-path rowid cost next393-400

This follow-on extends the current-source generated-path rowid cost selection aliases from next392 through next400.

- Reuse path: delegates to the accepted next236 implementation and aliases dependency, reader policy, selection keys, transition keys, and replan reasons to next393 through next400.
- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext393400Test.php`.
- Example self-test: `application-json-table-generated-path-rowid-cost-current-source-next393-400.php --self-test`.
- Non-overlap: this slice only opens next393-400 after merged next385-392 and keeps broad upstream suite evidence, unrelated planner rows, and private tmux state untouched.
