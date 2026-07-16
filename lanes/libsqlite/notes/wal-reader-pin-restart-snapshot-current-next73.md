# WAL Reader Pin Restart Snapshot current-next73

Slice: `wal-reader-pin-restart-snapshot-current-next73`.

## Behavior

- Added `SQLiteWalAppendPlan::checkpointRestartAppendReaderCurrentNext()` for the WAL edge where a current reader pins the first restart/truncate checkpoint, the reader is released, the retry checkpoint resets the WAL generation, and a follow-up writer appends the next committed import transaction.
- The current reader remains on its original snapshot while the next reader sees checkpointed database pages plus newly appended WAL frames in the restarted generation.
- This avoids accepted reader-pin append current-next69, restart/truncate handoff current-next68, checkpoint restart retry current-next54, WAL byte truncation, VFS savepoint rollback, rollback-journal commit, and VFS sync/apply clusters.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderPinRestartSnapshotCurrentNext73Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 52 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-reader-pin-restart-current-next73.php --self-test
```

## Dependency Closure

No new support component is needed. This composes existing native PHP WAL parsing, SHM read-mark parsing, durable checkpoint planning, and WAL append transaction byte generation.

## Next

A follow-up WAL slice can apply this restarted-generation append plan through the bounded VFS writer if the integrator wants real file-handle persistence evidence beyond this current/next snapshot behavior.
