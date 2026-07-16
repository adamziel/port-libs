# real-upstream-corpus-vfs-io-dynamic-20260531T000904Z-0

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test` and `/home/claude/port-libs/.upstream-cache/libsqlite/test/journal2.test`.
- Ported behavior cluster: dynamic VFS I/O traffic and SAFE_DELETE rollback-journal lifecycle behavior from upstream `io-2.*`, `io-3.*`, `io-4.*`, `io-5`, and `journal2-1.*` / `journal2-2.*`.
- Focused coverage: 40 upstream-derived TestRunner cases with 588 behavior assertions.
- Behavior fix: `SQLiteVfsIoDynamicPlan::ioTrafficPlan()` now treats sequential rollback-journal devices like upstream `io-3.*`: cache-spill traffic defers directory, journal-page, and journal-header syncs and commits with the single database-file sync that `io-3.3` expects.
- Non-overlap: does not add metadata-only runner rows and does not repeat accepted VFS file writer, sync apply, locked writer, process locks, rollback-journal commit/apply, checksum VFS, WAL checkpoint transactions, mmap pragma state, or old append-VFS coverage. This slice focuses on real upstream dynamic device-characteristic decisions and SAFE_DELETE journal operation sequencing.
- Verification:
  - `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` => no syntax errors.
  - `php -l lanes/libsqlite/tests/SQLiteVfsIoDynamicUpstreamCorpusTest.php` => no syntax errors.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsIoDynamicUpstreamCorpusTest.php` => `1 test files, 588 assertions, 0 failures`.
- Dependency closure: no new support component is needed; this reuses the existing native PHP VFS I/O dynamic planner and tightens its sequential sync behavior against upstream `io.test`.
