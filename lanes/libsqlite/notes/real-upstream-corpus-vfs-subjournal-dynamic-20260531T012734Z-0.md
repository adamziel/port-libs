# real-upstream-corpus-vfs-subjournal-dynamic-20260531T012734Z-0

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T012734Z-0`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/subjournal.test`
- Scenarios: `subjournal.test` `1.0`, `1.1`, `1.2`, `2.0`, `2.1`, `2.2`, `2.3`, and `2.4`.

## Change

- Added `SQLiteVfsIoDynamicPlan::subjournalMemoryBackupProfile()` for the upstream temp-store-memory statement subjournal contract: outer transaction rows remain visible after `ROLLBACK TO` an inner savepoint, statement before-images stay in memory, no disk statement journal is created, active online backup steps continue from `SQLITE_OK` to `SQLITE_DONE`, and source plus backup integrity checks remain `ok`.
- Added `SQLiteRealUpstreamCorpusVfsSubjournalDynamicTest.php` with 1,002 focused TestRunner PASS cases over the real upstream `subjournal.test` memory subjournal/backup behavior.

## Non-Overlap

This does not repeat accepted `memjournal.test`/`memjournal2.test` savepoint-loop coverage, `io.test`, `ioerr*.test`, auto-vacuum I/O error, sysfault, mmap, VFS file writer/sync/lock, rollback-journal apply/commit, super-journal, WAL checkpoint/savepoint, or metadata-only runner admission work. The owned upstream behavior is `subjournal.test` statement-subjournal rollback with an active backup handle.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` - pass.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSubjournalDynamicTest.php` - pass.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSubjournalDynamicTest.php` - `1 test files, 26012 assertions, 0 failures`, `1002` focused PASS lines.
- `git diff --check -- lanes/libsqlite` - pass.

## Dependency Closure

No new support component is needed. The patch reuses the existing bounded VFS I/O dynamic planning surface and adds a lane-local helper for statement-subjournal memory/backup behavior.
