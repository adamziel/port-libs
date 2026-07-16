# real-upstream-corpus-vfs-io-dynamic-20260530T210751Z-0

Base accepted HEAD: `140c9861a340b8e75fdc8ea93863883edb030323`.

Added `SQLiteRealUpstreamVfsIoDynamicExpandedCorpusTest.php` with 1,001 focused TestRunner cases and 10,501 behavior assertions. The batch extends the existing VFS/IO dynamic corpus without new public API or domain-specific names.

Upstream source files and sections:

- `tempdb.test` `tempdb-1.*` and `tempdb-2.*`: temporary database file open, deferred close, memory fallback, and delete-on-close behavior.
- `tempdb2.test`: temporary database lock-status interactions.
- `tempfault.test` `tempfault-3`: temp file cleanup after sorter/temp faults.
- `filectrl.test` `filectrl-1.*`: SQL/file-control callback behavior for mmap, chunk size, busy timeout, data version, name hint, and temp-file status.
- `lock.test` `lock-1.*` and `lock-2.*`: shared, reserved, and exclusive byte-range lock contention.
- `ioerr5.test`, `ioerr6.test`, and `pagerfault.test`: dynamic IO fault recovery, SHM full handling, pager error state, rollback requirement, and reopen behavior.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicExpandedCorpusTest.php`
- Result: `1 test files, 10501 assertions, 0 failures`

PASS-line movement:

- Before this patch: 0 PASS lines for `SQLiteRealUpstreamVfsIoDynamicExpandedCorpusTest.php`.
- After this patch: 1,001 PASS lines in the focused run.
- Expected `phpPass` movement: `718526 -> 719527`.

Dependency closure: no new support component is needed. The tests reuse existing native PHP VFS temp lifecycle, SQL file-control state, byte-range lock state, and dynamic IO fault recovery helpers.

Non-overlap: this is not another metadata admission row, suite ledger edit, or accepted WAL/pager/B-tree duplicate. It adds a new VFS/IO expanded corpus test file and cites real hydrated upstream SQLite `.test` sources.
