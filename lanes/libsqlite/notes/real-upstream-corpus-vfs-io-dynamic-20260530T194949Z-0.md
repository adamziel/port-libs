# Real Upstream Corpus VFS IO Dynamic 20260530T194949Z-0

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pagerfault2.test`
  - `pagerfault2-1-pre1`, `pagerfault2-1`: large rollback-to-savepoint after transient OOM while updating thousands of rows.
  - `pagerfault2-2-pre1`, `pagerfault2-2`: large BLOB insert under transient OOM releases statement-journal state cleanly.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pagerfault3.test`
  - `pagerfault3-pre1`, `pagerfault3-pre2`, `pagerfault3-1`: VACUUM page-size change with final-sync I/O error rolls back through a hot journal and extends the database image before integrity check.

## Patch

- Added `SQLiteVfsIoDynamicPlan::pagerFaultLargeRollbackProfile()` for bounded native PHP modeling of these pager/VFS fault outcomes.
- Extended `SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` with 4 focused PASS cases and 4,642 focused behavior assertions.

## Non-Overlap

This slice does not repeat accepted VFS file writer, locked writer, sync apply, rollback-journal apply/commit, WAL byte truncation, WAL/SHM fault, `ioerr.test`, `ioerr5.test`, `ioerr6.test`, `pagerfault.test`, `io.test` atomic/default-page-size, or appendvfs coverage. The new surface is specifically large pager rollback behavior from `pagerfault2.test` and `pagerfault3.test`.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`
  - `1 test files, 15372 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The patch reuses the existing bounded `SQLiteVfsIoDynamicPlan` real-upstream corpus helper and hydrated upstream SQLite `.test` files.
