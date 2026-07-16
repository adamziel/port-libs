# real-upstream-corpus-vfs-io-dynamic-20260530T225011Z-0

This slice adds a non-overlapping real upstream VFS I/O corpus batch from the hydrated SQLite upstream checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/autovacuum_ioerr2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/incrvacuum_ioerr.test`

Ported upstream sections:

- `autovacuum_ioerr2.test` `autovacuum-ioerr2-1`: full auto-vacuum delete, reinsert, schema-create, and commit I/O-error recovery.
- `autovacuum_ioerr2.test` `autovacuum-ioerr2-2`: full auto-vacuum overflow row delete/update with cache pressure and schema-create commit recovery.
- `autovacuum_ioerr2.test` `autovacuum-ioerr2-3`: full auto-vacuum drop-table root-page release and follow-up drop cleanup.
- `autovacuum_ioerr2.test` `autovacuum-ioerr2-4`: backup-restored full auto-vacuum large-row update commit recovery.
- `incrvacuum_ioerr.test` `incrvacuum-ioerr-1`: incremental auto-vacuum delete plus `PRAGMA incremental_vacuum` commit recovery.
- `incrvacuum_ioerr.test` `incrvacuum-ioerr-2`: repeated incremental-vacuum calls interleaved with delete, insert-select, index create/drop, and commit.
- `incrvacuum_ioerr.test` `incrvacuum-ioerr-3`: limited `PRAGMA incremental_vacuum(5)` after deleting overflow rows.
- `incrvacuum_ioerr.test` `incrvacuum-ioerr-4`: shared-cache incremental-vacuum page-count shrink equals freelist delta.

Implementation:

- Added `SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile()` to model auto-vacuum I/O fault handling, rollback attempts, freelist/page-count shrink accounting, pointer-map checks, integrity preservation, and open-file cleanup.
- Added `SQLiteRealUpstreamCorpusVfsAutoVacuumIoerrDynamicTest.php` with 1,202 focused TestRunner PASS cases and 26,448 assertions over 8 real upstream scenario roots x 30 failpoints x 5 VFS operations, plus citation and malformed-input guards.

Non-overlap:

- This does not repeat accepted `io.test` traffic/default-page-size coverage, `ioerr2.test`, `ioerr3.test`, `ioerr4.test`, `ioerr5.test`, `ioerr6.test`, `ioerr.test` pointer-map/overflow fault handling, `sysfault.test`, backup I/O error, mmap, walvfs, VFS lock/file-writer/sync/rollback-journal, or WAL checkpoint/savepoint clusters.
- The owned gap is the real upstream auto-vacuum I/O-error family in `autovacuum_ioerr2.test` and `incrvacuum_ioerr.test`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` - pass.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsAutoVacuumIoerrDynamicTest.php` - pass.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsAutoVacuumIoerrDynamicTest.php` - 1 test file, 26,448 assertions, 0 failures, 1,202 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` - not run; focused path is not present in this worktree.

Dependency closure: no new support component is needed. This reuses the lane-local VFS I/O dynamic corpus helpers and extends them with an auto-vacuum fault profile.
