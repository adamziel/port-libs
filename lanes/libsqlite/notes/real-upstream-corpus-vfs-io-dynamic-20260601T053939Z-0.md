# real-upstream-corpus-vfs-io-dynamic-20260601T053939Z-0

Status: ready for integration.

This slice adds non-overlapping real upstream VFS/file-format dynamic corpus coverage from the hydrated SQLite upstream checkout:

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/filefmt.test`.
- Covered upstream sections:
  - `filefmt-1.1` through `filefmt-1.8`: database header magic, page-size header field, invalid page-size rejection, and reserved-byte usable-space rejection.
  - `filefmt-2.1` and `filefmt-2.2`: compatibility with legacy 3.6.23.1-style writes that extend the file without refreshing the header page-count field, including savepoint rollback integrity.
  - `filefmt-3.1` through `filefmt-3.3`: auto-vacuum pointer-map compatibility after a legacy `DROP TABLE`.
  - `filefmt-4.1` through `filefmt-4.4`: backup integrity after a legacy auto-vacuum write.
- Focused growth: `1282` distinct TestRunner PASS cases and `37234` behavior assertions in `SQLiteRealUpstreamCorpusVfsFileFormatDynamicTest.php`.

Implementation:

- Added `SQLiteVfsIoDynamicPlan::fileFormatHeaderProfile()` for `filefmt.test` header, page-size, reserved-byte, legacy page-count, auto-vacuum, and backup compatibility behavior.
- Added a private upstream-section mapper so each profile cites the exact hydrated upstream scenario group.

Non-overlap:

- This owns only `filefmt.test` file-header/file-format compatibility behavior.
- It avoids accepted VFS writer/sync/lock-state/process-lock/lock-byte clusters, WAL checkpoint/savepoint/byte-truncation clusters, appendvfs, bigfile/bigfile2, file-control/tempfilename, checksum reserve, mmap, diskfull, quota, lock/sharedlock/superlock/nolock/win32lock, and ioerr/sysfault clusters.
- Mapped denominator remains unchanged because libsqlite already reports `1589 / 1589`; this handoff should count as PHP PASS-line/assertion growth only.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsFileFormatDynamicTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsFileFormatDynamicTest.php` -> `1 test files, 37234 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsFileFormatDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `3 test files, 102351 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` -> `lane-status json ok`.
- `git diff --check -- lanes/libsqlite` -> no output.

Dependency closure: no new support component is needed. This reuses the generic lane-local `SQLiteVfsIoDynamicPlan` VFS I/O corpus surface and the hydrated upstream `filefmt.test` source truth.
