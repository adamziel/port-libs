# WAL MVCC Checkpoint Hot-Journal Current Source Next107

## Behavior

Adds `SQLiteWalMvccCheckpointHotJournalCurrentSourceNextPlan`, a bounded current-source WAL/pager plan for a copied WordPress SQLite database that has both:

- a hot rollback journal from a crashed rollback-mode transaction; and
- a WAL sidecar with committed frames plus an uncommitted tail.

The plan verifies that the parsed rollback journal matches the current journal bytes, parses/checksums the current WAL bytes, restores the hot-journal database image before committed WAL checkpointing, compares dirty/current/next reader sources, and records that next readers use the checkpoint database image while the uncommitted WAL tail is discarded.

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalMvccCheckpointHotJournalCurrentSourceNext107Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 65 assertions, 0 failures
```

Expected focused `phpPass` delta: `+65` (`41873 -> 41938`) when integrated.

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-wal-mvcc-hotjournal-checkpoint-current-source-next107.php --self-test
```

The smoke reports copied `wp_options` import recovery where a pinned current reader sees committed WAL frames, hot-journal recovery precedes checkpointing, and the next reader uses the checkpointed database image.

## Non-Overlap

This avoids the accepted batch104/105 WAL checkpoint/restart/truncate savepoint reader visibility, pager hot-journal statement-cache recovery, WAL checksum/salt recovery, rollback-journal commit/apply, super-journal commit, and VFS file-writer/lock clusters. The new surface is the combined MVCC boundary across dirty current WAL, hot-journal-restored committed WAL, and post-checkpoint database source.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP rollback-journal, WAL parser/checksum, transaction recovery, and checkpoint image primitives.
