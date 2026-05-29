# JSON table generated-path rowid cost next457-472

This follow-on extends the current-source generated-path rowid cost selection aliases from next456 through next472.

- Reuse path: delegates to the accepted next236 implementation and aliases dependency, reader policy, selection keys, transition keys, and replan reasons to next457 through next472.
- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext457472Test.php`.
- Example self-test: `wordpress-json-table-generated-path-rowid-cost-current-source-next457-472.php --self-test`.
- Boundary handoff: the new focused test moves the no-later-alias assertion to next473.
- Non-overlap: this slice only opens next457-472 after merged next441-456 and keeps broad upstream suite evidence, unrelated planner rows, lane-status, progress, supervisor, and private tmux state untouched.
