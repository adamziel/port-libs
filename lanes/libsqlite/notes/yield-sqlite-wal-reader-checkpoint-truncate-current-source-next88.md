# WAL reader checkpoint truncate current-source next88

## Behavior

Adds `SQLiteWal::checkpointTruncateReaderCurrentSourceNext()` for the
truncate-checkpoint path where a current reader pins an older WAL snapshot. The
method validates the caller-provided current WAL bytes against the parsed source,
checks current-reader visibility, models the next reader while truncation is
blocked and the WAL is preserved, and models the drained retry where the WAL is
truncated and new readers use the checkpointed database image.

This is distinct from accepted `restartTruncateReaderCurrentSourceNext`, which
models drained restart/truncate from current source without a blocking reader,
and from accepted WAL reader-pin restart/truncate handoff surfaces.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteWal.php`
- `php -l lanes/libsqlite/tests/SQLiteWalReaderCheckpointTruncateCurrentSourceNext88Test.php`
- `php -l lanes/libsqlite/examples/wordpress-wal-reader-checkpoint-truncate-current-source-next88.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderCheckpointTruncateCurrentSourceNext88Test.php`
  - `1 test files, 68 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-wal-reader-checkpoint-truncate-current-source-next88.php`
  - reports `reader-pinned-truncate-preserves-wal`, `preserve_wal` while pinned,
    and `truncate_wal` after reader release.

## Dependency Closure

No new support component is required. The slice reuses existing native
`SQLiteWal`, WAL checksum/header parsing, durable checkpoint planning, and
reader visibility helpers.

## Non-Overlap

Avoids accepted batch85-86 WAL restart/truncate reader visibility and accepted
restart/truncate current-source next86 drained behavior by covering the pinned
truncate checkpoint where the current reader blocks WAL truncation but the next
reader still sees preserved WAL frames until a drained retry truncates the WAL.
