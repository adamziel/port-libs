# JSON table generated-path rowid cost next425-440

This follow-on extends the current-source generated-path rowid cost selection aliases from next424 through next440.

- Reuse path: delegates to the accepted next236 implementation and aliases dependency, reader policy, selection keys, transition keys, and replan reasons to next425 through next440.
- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext425440Test.php`.
- Example self-test: `application-json-table-generated-path-rowid-cost-current-source-next425-440.php --self-test`.
- Non-overlap: this slice only opens next425-440 after merged next409-424 and keeps broad upstream suite evidence, unrelated planner rows, lane-status, progress, supervisor, and private tmux state untouched.
