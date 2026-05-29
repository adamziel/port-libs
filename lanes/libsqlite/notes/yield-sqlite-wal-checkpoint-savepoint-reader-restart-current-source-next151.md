# WAL Checkpoint Savepoint Reader Restart Current Source Next151

## Behavior

- Adds `SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointReaderRestartCurrentSourceNext151()`.
- Models a `RESTART` checkpoint after `ROLLBACK TO` truncates savepoint-owned WAL frames while an existing reader still pins the old current source.
- Verifies the released SHM image unblocks reset, the restarted WAL header advances to a fresh current source, and a reopened reader sees the checkpointed database image at frame `0` before any retry writer appends new WAL frames.
- Rejects stale reader WAL bytes, stale current WAL bytes, missing active reader pins, unreleased SHM state, SHM salt mismatches, empty inputs, and non-integer page watches.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointSavepointReaderRestartCurrentSourceNext151Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 77 assertions, 0 failures
```

## WordPress Smoke

```text
php lanes/libsqlite/examples/wordpress-wal-checkpoint-savepoint-reader-restart-current-source-next151.php
wordpress-wal-checkpoint-savepoint-reader-restart-current-source-next151 self-test passed
```

## Non-Overlap

This avoids accepted next145 append-after-RESTART behavior by stopping at the reopened-reader current-source boundary before retry append, and avoids next146 TRUNCATE-reader restart by requiring `RESTART` mode with the retained restarted WAL header.

## Dependency Closure

No new support component is needed; this reuses native WAL parsing, savepoint WAL byte truncation, SHM read-mark planning, and checkpoint durable-result helpers.
