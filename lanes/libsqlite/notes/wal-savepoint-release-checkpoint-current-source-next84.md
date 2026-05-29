# WAL Savepoint Release Checkpoint Current Source Next84

Status: focused PHP behavior growth for WAL savepoint RELEASE checkpoint current-source admission.

## Behavior

- `SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentSourceNext()` now verifies that the parsed `SQLiteWal` object matches the raw current WAL bytes before checkpointing a released savepoint.
- The guard rejects stale raw WAL bytes with a different salt, different checkpoint sequence, or shorter frame set, and rejects a stale parsed WAL paired with current bytes.
- Safe matching WAL bytes still preserve release/current/next reader visibility for restarted, truncated, and reader-pinned checkpoint paths.

## Verification

```bash
php -l lanes/libsqlite/src/SQLiteWalSavepointCheckpointPlan.php
php -l lanes/libsqlite/tests/SQLiteWalSavepointReleaseCheckpointCurrentSourceNext84Test.php
php -l lanes/libsqlite/examples/wordpress-wal-savepoint-release-checkpoint-current-source-next84.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalSavepointReleaseCheckpointCurrentSourceNext84Test.php
php lanes/libsqlite/examples/wordpress-wal-savepoint-release-checkpoint-current-source-next84.php --self-test
git diff --check -- lanes/libsqlite
```

Focused test delta: +59 PASS lines in `SQLiteWalSavepointReleaseCheckpointCurrentSourceNext84Test.php`.

## Non-Overlap

Avoids accepted WAL savepoint byte truncation, VFS savepoint rollback application, WAL checkpoint transactions, WAL savepoint restart-checkpoint application, rollback-journal/super-journal paths, reader-pin/checksum/salt recovery slices, and next79 rollback-to current-source admission. This slice only guards the RELEASE checkpoint path before raw current WAL bytes are trusted.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP WAL parsing/checksum validation, savepoint release planning, reader visibility, and durable checkpoint planning.

## Next

Continue with broader pager/VFS transaction application or a distinct WAL checkpoint durability edge; avoid another release-checkpoint wrapper unless it applies a new source or durability rule.
