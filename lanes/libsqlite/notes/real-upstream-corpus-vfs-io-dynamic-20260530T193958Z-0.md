# real-upstream-corpus-vfs-io-dynamic-20260530T193958Z-0

Added `SQLiteRealUpstreamCorpusVfsIoErrorExtendedDynamicTest.php` with 1125
distinct focused TestRunner PASS cases and 17873 behavior assertions from real
hydrated SQLite upstream VFS I/O error scripts:

- `ioerr3.test`: `ioerr3-1` soft heap limit transaction cache writes and
  `ioerr3-2` temporary table creation.
- `ioerr4.test`: shared-cache incremental vacuum setup and `ioerr4-2`
  incremental vacuum fault injection.
- `ioerr5.test`: `ioerr5-1` and `ioerr5-2` pager error-state memory reclaim
  under normal and exclusive locking modes.
- `ioerr6.test`: atomic-write `SQLITE_FULL` fault behavior for insert and
  schema-create integrity checks.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoErrorExtendedDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoErrorExtendedDynamicTest.php`
  passed `1 test files, 17873 assertions, 0 failures` with 1125 PASS lines.

Non-overlap:

This extends the existing VFS I/O corpus past accepted `io.test` transaction
sequence/default-page-size coverage and the base `ioerr.test`, `ioerr2.test`,
`ioerr3`, `ioerr5`, and recovery-profile matrices. It does not change
production source, does not add mapped denominator rows, and does not touch
source-neutral cleanup or domain-specific API surfaces.

Dependency closure:

No new support component is needed. The slice reuses the existing native PHP
VFS I/O error outcome model and keeps the remaining closure path bounded to
additional hydrated upstream VFS/WAL fault scripts such as `walvfs.test`,
`walfault.test`, and `walrofault.test`.
