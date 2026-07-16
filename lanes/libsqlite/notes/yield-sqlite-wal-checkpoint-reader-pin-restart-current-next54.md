# WAL Checkpoint Reader Pin Restart Current/Next 54

## Behavior

- Added `SQLiteWal::checkpointReaderPinRestartRetryCurrentNext()` for the WAL restart/truncate retry edge where a current SHM read-mark pins an older reader snapshot.
- The first checkpoint attempt preserves the WAL while the read lock pins an old frame; the released-reader retry checkpoints committed frames and either restarts the WAL header or truncates the WAL for the next reader.
- The focused Application smoke uses copied `wp_options` page images for `siteurl`, autoload index, and plugin option pages.

## Focused Evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointReaderPinRestartCurrentNext54Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
56 PASS lines
1 test files, 56 assertions, 0 failures
```

## Status Delta

- `phpPass`: `19277 -> 19333` (`+56` verified PASS lines).
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior coverage, not a newly mapped upstream inventory unit.

## Non-Overlap

This slice avoids accepted WAL checkpoint transaction planning, read-mark restart reader-map visibility, savepoint checkpoint/yield behavior, WAL byte truncation, VFS file-writer application, rollback-journal application, and VFS sync/lock clusters. The new behavior specifically covers the current-reader pin followed by a released-reader restart/truncate retry.

## Dependency Closure

No new support component is required. The slice reuses the existing native PHP WAL parser/checkpoint helpers and SHM read-mark parser.
