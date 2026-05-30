# WAL hot-journal savepoint checkpoint current-source next206

Status: focused WAL/pager current-source behavior slice.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a post-next203 checkpoint generation fence for reopened Application prepared statements and page-cache consumers. It admits consumers only when their statement generation, database digest, WAL digest, root-page digests, savepoint depth, dirty/closed state, and hot-journal identity all match the current checkpoint generation.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext206Test.php`
- Expected focused movement: +63 PASS lines over the current clean worktree lane status (`98594 -> 98657`).
- `benchmarkDenominator.mapped` remains unchanged; this is current-source WAL/pager PHP behavior over already mapped WAL/checkpoint/savepoint inventory.

Application smoke:

- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next206.php`

Dependency closure: no new support component needed. The behavior reuses next203 WAL sidecar and checkpoint page digests to fence reopened prepared statements and page-cache consumers.

Non-overlap: next206 does not repeat WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, checkpoint transaction planning, WAL sidecar file writing, next203 lease digest checks, accepted hot-journal recovery, or accepted page-cache lease fencing.
