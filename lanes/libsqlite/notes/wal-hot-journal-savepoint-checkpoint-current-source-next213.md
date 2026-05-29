# WAL hot-journal savepoint checkpoint current-source next213

## Behavior

- Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission()`.
- Models the upstream WAL checkpoint rule that a later restart/reset checkpoint cannot reuse stale readers after hot-journal recovery and a savepoint-era PASSIVE checkpoint. Every stale reader named by next212 must reopen on the current database/WAL/writer/checkpoint digests, hold a shared-lock receipt, close savepoint scopes, drop hot-journal identity, and reach the restart target frame.
- Keeps active reader pins from authorizing the reset; they forced the earlier PASSIVE checkpoint to preserve the WAL and must not be counted as stale-reader reopen receipts.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext213Test.php`
- Result: `1 test files, 95 assertions, 0 failures`.
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next213.php`

## Non-overlap

This slice builds after next212 passive partial checkpoint handling. It does not repeat next212 passive frame selection, next209 writer fences, restart/truncate byte reset, VFS writer application, rollback-journal apply/commit, WAL byte truncation, or checkpoint transaction planning.

## Dependency closure

No new support component is needed. The slice reuses lane-local digest metadata, reader pin names, reopen receipts, and existing PHP array planning primitives.
