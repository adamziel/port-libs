# WAL checkpoint restart/truncate reader current-source next93

## Behavior

Adds `SQLiteWal::checkpointRestartTruncateReaderCurrentSourceNext()` for the
current-source WAL checkpoint path where the caller must prove the WAL sidecar
bytes still match the parsed WAL before planning restart/truncate reader
transitions. The method reports current, next, and final source generations:
the current reader stays on the verified sidecar snapshot, the next reader after
current-reader release uses checkpointed database pages while a later reader can
still pin the sidecar, and the final all-reader-release retry uses a restarted
header or empty truncated WAL generation.

This is distinct from accepted batch89 WAL reader checkpoint restart visibility:
that slice models the SHM/read-mark handoff. This slice adds explicit
current-source byte identity, source-generation transition rows, restart versus
truncate generation reporting, and stale sidecar rejection for the combined
restart/truncate path.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointRestartTruncateReaderCurrentSourceNext93Test.php`
  - `1 test files, 65 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteWal.php`
- `php -l lanes/libsqlite/tests/SQLiteWalCheckpointRestartTruncateReaderCurrentSourceNext93Test.php`
- `php -l lanes/libsqlite/examples/application-wal-checkpoint-current-source-next93.php`
- `php lanes/libsqlite/examples/application-wal-checkpoint-current-source-next93.php`
  - reports `reader-pin-next-reader-blocks-restart-current-source-next93` with current, next, and final source arrays.
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is required. The slice reuses existing native
`SQLiteWal`, `SQLiteShmIndex`, checksum/header parsing, durable checkpoint
planning, current-source admission, and reader visibility helpers.

## Non-Overlap

Avoids accepted WAL byte truncation, WAL checkpoint transaction, WAL reader-pin
handoff, and batch89 WAL reader checkpoint restart/truncate visibility by
covering the narrower current-source byte identity and generation transition
edge for both restart and truncate.
