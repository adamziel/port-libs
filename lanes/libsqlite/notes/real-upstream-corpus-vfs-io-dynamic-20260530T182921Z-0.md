Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T182921Z-0`

Added a real upstream VFS I/O dynamic reopen/fault corpus batch in
`SQLiteRealUpstreamVfsIoDynamicReopenFaultCorpusTest.php`.

Upstream source files and scenarios:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pagerfault2.test`
  - `pagerfault2-1` and `pagerfault2-2`: transient OOM fault handling across
    close/reopen and integrity verification.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pagerfault3.test`
  - `pagerfault3-1`: transient I/O fault handling after restore/reopen.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr2.test`
  - `ioerr2-3.*` and `ioerr2-4.*`: persistent-journal and rollback
    close/reopen recovery families.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr3.test`
  - `ioerr3-1` and `ioerr3-2`: create-index and statement-journal I/O error
    recovery.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr4.test`
  - `ioerr4-2`: auto-vacuum pointer-map recovery after I/O error injection.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/backup_ioerr.test`
  - backup source read/write I/O error close/reopen recovery.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicReopenFaultCorpusTest.php`
  - passed
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicReopenFaultCorpusTest.php`
  - `1 test files, 8212 assertions, 0 failures`

Countability:

- Adds 3 focused TestRunner PASS cases and 8212 behavior assertions from 432
  real upstream VFS/pager fault-recovery scenarios.
- Expected dashboard movement: `phpPass` +3, from 298721 to 298724. Mapped
  denominator coverage is unchanged.

Non-overlap:

- This avoids already accepted `io.test`, `avfs.test`, `cksumvfs.test`,
  `walvfs.test`, `ioerr5.test`, `ioerr6.test`, and `pagerfault.test` dynamic
  surfaces. The new batch is specifically close/reopen fault recovery from
  `pagerfault2.test`, `pagerfault3.test`, `ioerr2.test`, `ioerr3.test`,
  `ioerr4.test`, and `backup_ioerr.test`.
- It does not repeat accepted VFS file writer, locked writer, process-lock,
  sync-plan/apply, rollback-journal apply/commit, WAL byte truncation,
  checkpoint transaction, JSON, B-tree, SQL executor, or source-neutral cleanup
  clusters.

Dependency closure:

- No new support component is needed. The batch reuses the existing bounded
  VFS dynamic fault planner and focused TestRunner harness.

Root harness:

- Not run - isolated micro-slice.
