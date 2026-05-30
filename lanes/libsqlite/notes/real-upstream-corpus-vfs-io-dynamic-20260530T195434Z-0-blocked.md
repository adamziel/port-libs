# real-upstream-corpus-vfs-io-dynamic-20260530T195434Z-0 blocked

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T195434Z-0`
Base accepted HEAD: `a279204339e8bc1ec8d0d4db06bea5b6a6d043b5`

## Upstream sections checked

Hydrated upstream source truth was read from
`/home/claude/port-libs/.upstream-cache/libsqlite/test`.

Candidate VFS / pager I/O files and top-level Tcl scenario counts:

- `io.test`: 36 top-level `do_*test` rows.
- `ioerr.test`: 10 top-level rows.
- `ioerr2.test`: 3 top-level rows.
- `ioerr3.test`: 2 top-level rows.
- `ioerr4.test`: 7 top-level rows.
- `ioerr5.test`: 4 top-level rows.
- `ioerr6.test`: 2 top-level rows.
- `atomic.test`: 3 top-level rows.
- `atomic2.test`: 1 top-level row.
- `avfs.test`: 16 top-level rows.
- `cksumvfs.test`: 10 top-level rows.
- `walvfs.test`: 34 top-level rows.

## Current-base overlap

The current accepted base already has dedicated generic PHP VFS/io corpus
support and tests for this exact domain:

- `SQLiteVfsIoDynamicPlan`
- `SQLiteVfsIoTrafficPlan`
- `SQLiteVfsIoTransactionSequencePlan`
- `SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`
- `SQLiteRealUpstreamCorpusVfsIoTransactionSequenceTest.php`
- `SQLiteRealUpstreamCorpusVfsIoErrorDynamicTest.php`
- `SQLiteRealUpstreamCorpusVfsWalShmDynamicTest.php`
- plus focused `ioerr2`, `ioerr3`, `atomic2`, reopen-fault, quick-balance,
  default-page-size, WAL/SHM, appendvfs, checksum-reserve, journal-size-limit,
  atomic-journal, nolock, and file-control sequence coverage.

Focused verification of the existing selected family passed:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoTransactionSequenceTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoErrorDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsWalShmDynamicTest.php
4 test files, 51712 assertions, 0 failures
```

That run includes existing accepted PASS cases such as the `walvfs-9` dynamic
attempt matrix through `real upstream corpus vfs wal shm dynamic 1320`, but
those rows are not new for this worker and must not be counted as fresh PASS
growth.

## Blocker

The hard handoff floor for fresh `real-upstream-corpus-*` workers requires at
least 1,000 new distinct focused TestRunner PASS cases, 5,000 behavior
assertions, a named blocker that unlocks at least 2,000 PASS cases / 10,000
assertions, or real mapped denominator movement. A small additive hand-port in
the already-covered `io.test` / `ioerr*.test` / `atomic*.test` / `avfs.test` /
`cksumvfs.test` / `walvfs.test` helper surface would be duplicate or below the
active floor.

This slice is therefore blocked from a ready implementation handoff. The next
useful larger batch should avoid the already-covered helper wrappers and target
one of:

- a guarded upstream-runner admission batch for remaining VFS/pager scripts not
  represented in PHP, with non-overlapping mapped denominator evidence;
- a real behavior fix found by running a fresh bounded upstream shard that
  unlocks at least 2,000 selected PASS cases; or
- a new native pager/VFS primitive not represented by the current helper
  models, then a large real upstream corpus batch against that primitive.

Dependency closure: no new support component was added in this blocked slice.
The current obstruction is overlap and floor size, not a missing dependency.
