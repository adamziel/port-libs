# real-upstream-corpus-vfs-io-dynamic-journal1-stale-rollback-20260531

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T050806Z-0`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/journal1.test`
- Scenarios: `journal1-1.1` and `journal1-1.2`.

Implementation:

- Added `SQLiteVfsIoDynamicPlan::staleRollbackJournalNewDatabaseProfile()` for the upstream rollback-journal identity contract: a rollback journal copied from a prior database image is present after the original database file is deleted, but opening a new database at the same path must ignore the stale journal instead of replaying it into the new image.
- Added `SQLiteRealUpstreamCorpusVfsJournal1StaleRollbackDynamicTest.php` with 1,000 dynamic focused PASS cases over upstream row-count and payload-size variants, plus source-citation, upstream guard, and malformed-input checks.

Non-overlap:

- This owns `journal1.test` stale rollback-journal/new-database behavior only.
- It does not repeat accepted `journal2.test` SAFE_DELETE ordering/hot-journal recovery, `journal3.test` permission inheritance, `io.test` atomic/default-page-size/sync/traffic matrices, `ioerr*` fault recovery, append VFS, checksum VFS, memory/subjournal, VFS writer/sync/lock/rollback-journal apply/commit, WAL checkpoint/savepoint, or pager/B-tree/SQL/JSON clusters.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsJournal1StaleRollbackDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsJournal1StaleRollbackDynamicTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is required. The slice reuses the existing bounded VFS I/O dynamic planning surface and adds one native PHP profile for the real upstream `journal1.test` stale rollback-journal identity behavior.
