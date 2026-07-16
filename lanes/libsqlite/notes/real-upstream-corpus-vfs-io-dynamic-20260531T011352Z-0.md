# Real Upstream Corpus VFS I/O Dynamic Quick Balance

- Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T011352Z-0`
- Base accepted HEAD: `87abcd98ff24a32f5554f16930fc2af1462cc57c`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
- Upstream scenarios: `io.test io-1.1`, `io.test io-1.2`, `io.test io-1.3`, `io.test io-1.4`, `io.test io-1.5`
- Focused coverage: `SQLiteRealUpstreamCorpusVfsIoDynamicQuickBalanceTest.php`
- Focused result: `1 test files, 4062 assertions, 0 failures`, `202` PASS lines.

This slice keeps the canonical upstream `io.test` quick-balance shape
for 1024-byte pages and 230-byte payload rows, then ports the same
root-leaf fill, split, ordinary leaf append, and quick-balance write-count
behavior across bounded page-size, payload-size, and row-count boundaries.

Non-overlap: avoids accepted VFS mmap/reopen-fault/ioerr/journal2/safe-append,
VFS file writer, rollback-journal apply/commit, sync apply, lock-state/process
lock, and app-WAL parked surfaces. It extends `io.test io-1.*` dynamic write
profile behavior only.

Dependency closure: no new support component is needed. This reuses the
existing lane-local VFS I/O traffic planning helpers and the hydrated upstream
SQLite Tcl corpus as source truth.
