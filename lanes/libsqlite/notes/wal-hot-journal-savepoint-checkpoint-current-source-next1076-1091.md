# WAL hot-journal savepoint checkpoint current-source next1076-1091

Status: focused behavior growth for the consolidated after-current WAL checkpoint
receipt chain.

This slice extends `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`
from accepted `next1075` through `next1091` without adding a duplicate numbered
class. The rows continue the restart salt, reader release, page-cache,
schema-cookie, commit-generation, WAL-index salt, hot-journal absence, and
seal receipt sequence.

Validation targets:

- `tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext10601075Test.php`
- `tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext10761091Test.php`
- `examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next1091.php --self-test`

Non-overlap: next1076-1091 only advances the already consolidated after-current
checkpoint receipt chain after next1060-1075. It does not add new VFS, B-tree,
JSON, SQL planner, dashboard, or suite-runner behavior.
