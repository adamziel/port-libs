# Real Upstream Corpus VFS IO Dynamic 20260530T224121Z-0

- Base accepted HEAD: `dc9a740fd34e07dba61e9143b3604d183ad170bf`.
- Scope: VFS I/O dynamic behavior from hydrated upstream SQLite tests in `/home/claude/port-libs/.upstream-cache/libsqlite/test`.
- Added focused PHP test file: `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicThousandTest.php`.
- Upstream source files and sections:
  - `io.test`: `io-2.6.1` through `io-2.11.2` atomic write, multi-file, rollback, journal failure, exclusive-lock, and deferred journal admission behavior.
  - `ioerr.test`: `ioerr-13`, `ioerr-14`, and `ioerr-16` pointer-map, root split, balance-quick, and incremental-vacuum fault recovery.
  - `ioerr2.test`: rollback invariants, update-under-select fault handling, and temp-store directory fault handling.
  - `ioerr3.test`: `ioerr3-1` and `ioerr3-2` soft-heap-limit pager cache, transaction, and temp-table fault behavior.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicThousandTest.php` passed with `1 test files / 18501 assertions / 0 failures` and `1001` PASS lines.
- Non-overlap: this batch does not add metadata-only runner rows and does not repeat accepted VFS checksum/WAL, default-page-size, mmap, walvfs, lock-contention, rollback-journal apply, VFS locked writer, or sync-apply files. It broadens real upstream dynamic `io.test` atomic admission and `ioerr*.test` recovery matrices with distinct parameters and assertions.
- Dependency closure: no new support component is needed; the batch reuses existing native PHP VFS and pager planning helpers.
