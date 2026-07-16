# JSON table generated-path rowid cost next369-376

This follow-on extends the current-source generated-path rowid cost selection aliases from next368 through next376.

- Scope: `json_tree`/`json_each` generated path scans over copied Application option JSON rows.
- Reuse path: delegates to the accepted next236 implementation and aliases dependency, reader policy, selection keys, transition keys, and replan reasons to next369 through next376.
- Guarded behavior: current pinned rowid point-cost selections keep estimated cost `1`; changed generated paths or source generations keep the next reader on the reprepare path.
- Support surface: no new support component is required.
