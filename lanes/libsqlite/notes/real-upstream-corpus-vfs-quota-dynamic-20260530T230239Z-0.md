# real-upstream-corpus-vfs-io-dynamic-20260530T230239Z-0

Added a non-overlapping real upstream VFS I/O corpus batch from the hydrated
SQLite checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/quota.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/quota2.test`

Ported upstream sections:

- `quota.test` `quota-2.1`, `quota-2.2`, and `quota-2.4`: single rollback-mode quota group, over-limit `SQLITE_FULL`, callback limit extension, and shutdown misuse while a quota handle is still open.
- `quota.test` `quota-3.1`, `quota-3.2`, and `quota-3.3`: two connections to one quota file plus multiple database files sharing one quota group and callback accounting for the file crossing the quota.
- `quota2.test` `quota2-1`, `quota2-2`, and `quota2-3`: quota `fopen`/`fwrite`/`fread`/`ftruncate` lifecycle, untracked-file bypass behavior, and append-mode quota accounting.

Implementation:

- Added `SQLiteVfsIoDynamicPlan::quotaVfsLimitProfile()` for quota VFS limit accounting, callback extension, over-limit result codes, open-handle shutdown behavior, VFS name decoration, group size accounting, and upstream citation.
- Added `SQLiteRealUpstreamCorpusVfsQuotaDynamicTest.php` with 1,084 focused TestRunner PASS cases and 24,857 assertions over 9 real upstream quota roots x 120 dynamic size/handle variants plus citation, shutdown, and malformed-input guards.

Non-overlap:

- This does not repeat accepted `io.test` traffic/default-page-size, `ioerr*`, `sysfault.test`, mmap, backup I/O, walvfs, lock contention, auto-vacuum I/O-error, VFS file writer/sync/rollback-journal, or WAL checkpoint/savepoint clusters.
- The owned gap is quota VFS I/O accounting and file-handle quota lifecycle from real upstream `quota.test` and `quota2.test`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` - pass.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsQuotaDynamicTest.php` - pass.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsQuotaDynamicTest.php` - 1 test file, 24,857 assertions, 0 failures, 1,084 PASS lines.

Dependency closure: no new support component is needed. This reuses the lane-local VFS I/O dynamic corpus helper surface and adds bounded native PHP quota-VFS accounting behavior.
