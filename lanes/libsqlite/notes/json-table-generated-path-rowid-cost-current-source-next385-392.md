# JSON table generated-path rowid cost next385-392

This follow-on extends the current-source generated-path rowid cost selection aliases from next384 through next392.

- Reuse path: delegates to the accepted next236 implementation and aliases dependency, reader policy, selection keys, transition keys, and replan reasons to next385 through next392.
- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext385392Test.php`.
- Example self-test: `wordpress-json-table-generated-path-rowid-cost-current-source-next385-392.php --self-test`.
- Non-overlap: this slice only opens next385-392 after merged next377-384 and keeps broad upstream suite evidence, unrelated planner rows, and private tmux state untouched.
