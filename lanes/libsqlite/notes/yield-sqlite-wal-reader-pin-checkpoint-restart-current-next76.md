# WAL Reader Pin Checkpoint Restart Current/Next 76

## Behavior

- Added `SQLiteWal::checkpointReaderPinRestartCurrentNext()` for the WAL RESTART/TRUNCATE edge where an old reader pins checkpoint completion, a newer reader attaches at the current WAL end, and releasing only the old reader still blocks WAL reset.
- The helper reports current, next, and final reader page visibility so Application import diagnostics can distinguish the old snapshot from the newer WAL reader and the final checkpointed database image after all readers drain.
- The focused Application smoke uses copied `wp_options` page images for site URL, autoload index, plugin settings, and transient pages.

## Focused Evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderPinCheckpointRestartCurrentNext76Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
59 PASS lines
1 test files, 59 assertions, 0 failures
```

## Status Delta

- `phpPass`: `28917 -> 28976` (`+59` verified PASS lines in this isolated worktree).
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior coverage, not a newly mapped upstream inventory unit.

## Non-Overlap

This slice avoids the accepted WAL reader-pin restart/truncate handoff, WAL reader-pin database/WAL snapshot handoff, WAL checksum/salt recovery, SHM readmark recovery, savepoint byte truncation, VFS savepoint rollback application, rollback-journal apply/commit, WAL checkpoint transaction planning, and WAL checkpoint crash recovery. The new behavior specifically covers a newer read mark that continues to block RESTART/TRUNCATE reset after the older pin is released.

## Dependency Closure

No new support component is required. The slice reuses the existing native PHP WAL parser/checkpoint helpers and SHM read-mark parser.
