# real-upstream-corpus-vfs-io-dynamic journal2 SAFE_DELETE

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T010338Z-0`

Accepted base: `db598d2f37de4eb8809eabdfe8470ae863639e6e`

Widened the existing focused real-upstream VFS/IO corpus coverage for `journal2.test` SAFE_DELETE rollback-journal lifecycle behavior:

- `journal2.test journal2-1.1` create-table journal open/close/delete.
- `journal2.test journal2-1.2` through `journal2-1.4` truncate-mode journal reuse without delete.
- `journal2.test journal2-1.5` through `journal2-1.9` delete blocked while a journal handle is open.
- `journal2.test journal2-1.10` through `journal2-1.21` large-commit IOERR leaves a hot journal and recovers the committed image.
- `journal2.test journal2-2.1` through `journal2-2.4` WAL transition deletes persistent rollback journals when WAL is available.

Focused result:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsJournal2SafeDeleteDynamicTest.php`
- `1 test files, 44007 assertions, 0 failures`
- 2002 distinct TestRunner PASS cases.
- Non-overlapping growth: +1000 TestRunner PASS cases over the existing 1002-case focused file.

Non-overlap:

- Uses existing `SQLiteVfsIoDynamicPlan::safeDeleteJournalLifecycle()` behavior and expands the accepted 1000-case matrix to 2000 behavior cases.
- Does not repeat accepted appendvfs, cksumvfs, walvfs SHM, lock-state, file-writer, sync, rollback-commit, super-journal, or VFS open/file-control slices.

Dependency closure:

- No new support component is needed. The slice reuses the bounded VFS IO dynamic plan and existing test runner.
