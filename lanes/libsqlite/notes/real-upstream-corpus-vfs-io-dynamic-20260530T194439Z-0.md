# real-upstream-corpus-vfs-io-dynamic-20260530T194439Z-0

Base accepted HEAD: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
- Sections ported: `io-3.1`, `io-3.2`, `io-3.3`, `io-4.1`, `io-4.2.1`, `io-4.2.2`, `io-4.2.3`, `io-4.3.1`, `io-4.3.2`, `io-4.3.3`, `io-4.3.4`.

Behavior added:

- `SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile()` models upstream sequential-device cache-spill behavior from `io-3`: database file growth during spills, zero precommit syncs, and one database sync at commit.
- The same profile models upstream safe-append behavior from `io-4`: directory/page/database sync target shape, `0xFFFFFFFF` journal-header `nRec`, one journal header across cache spills, and journal file byte sizing as `512 + (page-size + 8) * page-count`.

Focused assertion delta:

- Added 3 focused TestRunner PASS cases.
- Added 13,410 behavior assertions from real upstream VFS I/O cases.
- Non-overlap: this does not add metadata-only admission rows and does not repeat accepted VFS file-writer, locked-writer, lock-state, rollback-journal apply, default page-size, atomic visibility, or existing safe-append journal-size assertions. The new matrix covers cache-spill sync profiles across page sizes, cache sizes, dirty page counts, sequential devices, safe-append devices, combined flags, sync-off, reserved-byte, and no-directory-sync variants.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` -> 1 file / 38 PASS cases / 24,560 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` -> guard not present in this worktree.
- `git diff --check -- lanes/libsqlite` -> clean.

Dependency closure:

- No new support component is needed. The slice extends the existing generic VFS I/O dynamic planner and reuses existing upstream-device characteristic normalization.
