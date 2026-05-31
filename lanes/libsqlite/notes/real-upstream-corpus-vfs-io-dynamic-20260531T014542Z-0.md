# real-upstream-corpus-vfs-io-dynamic-20260531T014542Z-0

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T014542Z-0`

Accepted base: `d0e37b664c0ef9500748faeafd4d7f1484470255`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
- Scenarios: `io-2.5.1`, `io-2.5.2`, and `io-2.5.3`.

Behavior added:

- Added `SQLiteVfsIoDynamicPlan::atomicMultiPageJournalProfile()` for the
  upstream atomic-write boundary where an atomic-capable device may avoid a
  rollback journal for the first dirty page, but must create and sync the
  rollback journal once the transaction dirties a second database page.
- Added 1,000 dynamic matrix cases across page sizes, atomic capability flags,
  sector sizes, sync modes, directory-sync availability, second-write sizes,
  and blocked journal-open rollback outcomes.
- Added one source-citation case. Focused movement is 1,001 TestRunner PASS
  cases and 28,001 assertions.

Non-overlap:

- This avoids accepted VFS file writer, locked writer, lock-state, process
  locks, rollback-journal apply/commit, super-journal, sync plan/apply,
  atomic-device visibility/admission for `io-2.6` through `io-2.11`,
  `io-3`/`io-4` sync optimizations, default page size, pager-cache retention,
  VFS mmap, `ioerr*`, journal2 safe-delete, WAL/SHM, and traffic-matrix
  batches.
- The owned upstream section is the previously unclaimed `io.test` `io-2.5.*`
  multi-page atomic-write rollback-journal boundary.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoAtomicMultiPageDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoAtomicMultiPageDynamicTest.php`
  - `1 test files, 28001 assertions, 0 failures`

Dependency closure:

- No new support component is required. The patch reuses the existing VFS I/O
  dynamic planning surface and adds a bounded native PHP helper for the real
  upstream `io-2.5.*` pager/VFS behavior.
