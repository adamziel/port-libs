# real-upstream-corpus-vfs-io-dynamic-20260531T070822Z-0

Base accepted HEAD: `96c3c12f0e388eba581b5758d55cd85f17d538ef`.

This slice adds a real upstream VFS/I/O dynamic wide-batch test file backed by the hydrated SQLite upstream checkout in `/home/claude/port-libs/.upstream-cache/libsqlite/test`.

Upstream source sections:

- `io.test` `io-2.*`: atomic-write optimization, journal admission, multi-page fallback, blocked journal paths.
- `io.test` `io-3.*`: sequential VFS devices deferring journal sync traffic.
- `io.test` `io-4.*`: safe-append journal header sizing and cache-spill sync behavior.
- `io.test` `io-5.*`: default page size selected from sector size and atomic-capability flags.
- `io.test` `io-6.*`: pager-cache retention after atomic-write transactions.
- `avfs.test` `avfs-1.*` through `avfs-3.*`: append VFS offset alignment, content persistence, growth, shrink, and reopen integrity.
- `cksumvfs.test` `1.0` through `1.9`: checksum VFS reserve bytes across bulk insert, WAL delete/checkpoint, recursive insert, and reopen.
- `syscall.test` `syscall-8.2` and `syscall-8.4`: file-control size-hint chunk growth.

Focused count:

- Added `9,956` generated TestRunner cases plus 2 guard/source tests in `SQLiteRealUpstreamCorpusVfsIoDynamicWideBatchTest.php`.
- The cases exercise existing VFS dynamic behavior helpers with varied page sizes, sector sizes, atomic/safe-append/sequential flags, journal modes, sync modes, append offsets, checksum reserve bytes, and size-hint chunk boundaries.

Non-overlap:

- This does not touch `ioerr.test`/`ioerr2.test`/`ioerr3.test`/`ioerr4.test` error-injection coverage already present in `SQLiteRealUpstreamCorpusVfsIoErrorDynamicBatchTest.php`.
- This does not repeat accepted VFS file writer, rollback-journal apply, sync apply, process-lock, lock-state, lock-byte-range, or WAL byte-truncation helper clusters.
- No source API names are changed and no WordPress-specific libsqlite API is added.

Dependency closure:

- No new support component is needed. The slice reuses existing bounded native PHP VFS/I/O dynamic planning helpers and ports upstream behavior into lane-local PHP tests.

Verification to record:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicWideBatchTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicWideBatchTest.php`
- `git diff --check -- lanes/libsqlite`
