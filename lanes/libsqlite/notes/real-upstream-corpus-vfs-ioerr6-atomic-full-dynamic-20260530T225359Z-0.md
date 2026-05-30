## real-upstream-corpus-vfs-ioerr6-atomic-full-dynamic-20260530T225359Z-0

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T225359Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr6.test`
- Scenarios: `ioerr6` 1.1, faultsim group 2, and faultsim group 3.

Behavior ported:

- Added a focused dynamic PHP corpus for atomic-write `SQLITE_FULL` and pager
  recovery behavior from `ioerr6.test`.
- The batch covers the insert, primary-key schema-create, and two-table
  schema-create sections across write/sync/truncate/delete/access/open/close
  fault operations, transient and persistent fault modes, 48 failpoints per
  operation, expected SQLite result codes, pager recovery actions, dirty-page
  preservation, integrity/refcount/checksum expectations, and provenance.

Focused assertion/pass movement:

- New TestRunner cases: 2021.
- Behavior assertions: 40,341 in the new file.
- Expected selected PASS-line growth: +2021, because the test file is new in
  this worktree.

Non-overlap:

- This does not repeat accepted append VFS, checksum VFS, WAL VFS, mmap,
  backup I/O, `io.test` traffic/default-page-size/cache-spill, `ioerr2`,
  `ioerr3`, `ioerr4`, `ioerr5`, broad mixed `ioerr6` dynamic matrices,
  pointer-map fault coverage, VFS file writer, lock-state, process locks,
  sync, rollback-journal apply/commit, or WAL checkpoint/savepoint clusters.
- The owned upstream surface is the atomic-write/full-fault behavior from
  `ioerr6.test` sections 1.1, 2, and 3 with a larger failpoint matrix than the
  existing mixed VFS dynamic tests.

Dependency closure:

- No new support component is needed. The slice reuses the lane-local
  `SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome()` planner and existing
  TestRunner infrastructure.
