# WAL Reader Restart Current/Next Slice

## Behavior

This slice adds `SQLiteWal::restartCurrentNextReaderVisibility()` for bounded
WAL restart/truncate checkpoint diagnostics. It composes the existing SHM
read-mark restart plan with concrete page-image visibility for:

- the current reader pinned by an active read mark;
- the next reader after restart/truncate admission;
- database-only visibility after truncate or restarted empty-WAL visibility;
- stale/current read-mark reuse across restart readiness.

The behavior is intentionally narrower than accepted WAL byte truncation,
checkpoint transaction admission, VFS file writing, sync application,
rollback-journal apply, and read-mark-only diagnostics. It does not write files
or mutate sidecars; it makes the current/next reader snapshot boundary
inspectable before pager/VFS application.

## Focused Evidence

Command:

`php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalRestartCurrentNextTest.php`

Result:

`1 test files, 420 assertions, 0 failures`

PASS-line delta: `+42` focused `TestRunner` PASS cases.

## Application Smoke

`lanes/libsqlite/examples/application-wal-reader-restart-current-next.php`
reports copied `wp_options`-style WAL frames where a theme/settings reader is
pinned at frame 2 while the next reader advances to frame 4 after restart
admission.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP WAL,
SHM read-mark, and checkpoint primitives.

## Non-Overlap

Avoids accepted `SQLitePagerCheckpointTransactionPlan`,
`SQLiteVfsFileWriter`, `SQLiteVfsSyncPlan`/apply,
`SQLiteVfsFileLock`/locked writer, rollback-journal commit/apply,
WAL savepoint byte truncation, WAL read-mark-only restart diagnostics, and
checkpoint current-reader visibility helpers.
