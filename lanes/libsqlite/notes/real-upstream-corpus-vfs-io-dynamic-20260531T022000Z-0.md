# real-upstream-corpus-vfs-io-dynamic-20260531T022000Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/journal1.test`
- Covered sections: `journal1-1.1` and `journal1-1.2`.

Behavior ported:

- Added `SQLiteVfsIoDynamicPlan::staleRollbackJournalIsolationProfile()` for the upstream stale rollback-journal isolation contract: create a database, create and save a rollback journal from a delete transaction, delete the database, copy the old journal beside a newly created database with the same name, and confirm the stale journal is not replayed into the new database.
- Added `SQLiteRealUpstreamCorpusVfsJournal1StaleRollbackDynamicTest.php` with 1,000 dynamic cases plus source-citation and malformed-input guard cases.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsJournal1StaleRollbackDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsJournal1StaleRollbackDynamicTest.php` passed: `1 test files, 22010 assertions, 0 failures`, `1002` PASS lines.

Non-overlap:

- This does not repeat accepted VFS file writer, locked writer, sync plan/apply, process locks, lock-state, rollback-journal apply/commit, super-journal commit, appendvfs, cksumvfs, memory journal, subjournal, sysfault, ioerr, atomic/crash, WAL checkpoint/savepoint, or journal2/journal3 batches.
- The owned upstream gap is `journal1.test` stale rollback journals beside recreated databases.

Dependency closure:

- No new support component is required. This reuses the lane-local VFS I/O dynamic planner surface and adds a bounded native PHP behavior profile for stale rollback-journal hotness isolation.
