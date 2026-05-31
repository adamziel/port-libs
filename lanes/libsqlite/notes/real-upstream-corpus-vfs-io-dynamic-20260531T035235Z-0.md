## real-upstream-corpus-vfs-io-dynamic-20260531T035235Z-0

Base accepted HEAD: `1d87a6fc2cf9c016da25d4e727af365cff780442`.

Added a real upstream VFS I/O quick-balance write-count slice from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`.

Covered upstream sections:

- `io.test` `io-1.1`: schema/table creation writes the schema page and table root page.
- `io.test` `io-1.2`: full-root leaf inserts write the table root plus change-counter page.
- `io.test` `io-1.3`: root split writes two leaf pages, root page, and change-counter page.
- `io.test` `io-1.4`: post-split leaf appends write the leaf plus change-counter page.
- `io.test` `io-1.5`: rightmost quick-balance append writes only the root, new leaf, and change-counter pages.

Focused movement:

- Added `SQLiteVfsIoDynamicPlan::quickBalanceWriteProfile()`.
- Added `SQLiteRealUpstreamCorpusVfsIoQuickBalanceDynamicTest.php` with `1002`
  focused TestRunner PASS cases and `24005` behavior assertions.
- Expected lane-status `phpPass` movement: `1932390 -> 1933392`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoQuickBalanceDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoQuickBalanceDynamicTest.php` passed:
  `1 test files, 24005 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Non-overlap:

This targets `io.test` quick-balance write-count behavior and avoids the
already accepted sysfault, mmap, temp lifecycle, file-control, lock matrix,
ioerr2/3/4/5/6, pointer-map fault, backup I/O, walvfs, VFS writer, lock-state,
process-lock, sync, rollback-journal application, and delete_db sidecar
clusters.

Dependency closure:

No new support component is needed. The slice reuses the existing generic VFS
I/O dynamic planning surface and adds a bounded quick-balance write-count model
backed by real upstream `io.test` sections.
