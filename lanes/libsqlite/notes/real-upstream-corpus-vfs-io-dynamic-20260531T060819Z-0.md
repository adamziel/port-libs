# real-upstream-corpus-vfs-io-dynamic-20260531T060819Z-0

Base accepted HEAD: cd24ba2f7b741bb89ced6cb6c27264084794565b.

Owned upstream files and sections:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr.test`
- `ioerr-4`: I/O failures while reading a record header that crosses onto an overflow page.
- `ioerr-8`: I/O failures while reading a short field that fits the Mem buffer path.

Patch summary:

- Added `SQLiteVfsIoDynamicPlan::recordReadIoErrorProfile()` for the record-read I/O error boundary.
- Added `SQLiteRealUpstreamCorpusVfsIoRecordReadDynamicTest.php` with 1,000 distinct TestRunner cases over page size, record width, selected column, inline payload size, overflow payload size, and injected read-fault index.

Non-overlap:

- Avoids accepted atomic I/O, quick-balance, append VFS, default page-size, safe-append, cache-spill, pointer-map, WAL/SHM, mmap, syscall, and journal playback slices.
- This slice only models `ioerr.test` record-read failures from `ioerr-4` and `ioerr-8`.

Dependency closure:

- No new support component is needed. The batch reuses the existing VFS dynamic-plan model and TestRunner harness.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php && php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoRecordReadDynamicTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoRecordReadDynamicTest.php`: 1 test files, 25011 assertions, 0 failures; 1003 PASS lines.
- `git diff --check -- lanes/libsqlite`: passed with no output.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`: lane-status json ok.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`: not run; guard file is absent in this worktree.
