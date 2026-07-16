# real-upstream-corpus-vfs-io-dynamic-20260531T093536Z-0

Status: ready focused PHP behavior growth for the VFS/I/O dynamic corpus.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/diskfull.test`
- Scenarios: `diskfull-1.1` setup, `diskfull-1.2` initial integrity, `diskfull-1.3` full-disk `INSERT INTO t1 SELECT * FROM t1`, `diskfull-1.4` post-insert integrity, `diskfull-1.5` full-disk `DELETE FROM t1`, `diskfull-1.6` post-delete integrity, and `diskfull-2` `do_diskfull_test VACUUM` close/reopen/integrity loop.

Implementation:

- Added `SQLiteVfsIoDynamicPlan::diskFullRecoveryProfile()` to model SQLite's full-disk VFS fault boundary, including fault-hit write indexes, result normalization from `disk I/O error` to `database or disk is full`, rollback/recovery state, stable database image expectations, and post-reopen integrity checks.
- Added `SQLiteRealUpstreamCorpusVfsDiskfullDynamicTest.php` with 1,203 distinct TestRunner PASS cases and 40,814 assertions over insert-select, delete, and VACUUM full-disk probes.

Non-overlap:

- Avoids accepted VFS bigfile sparse/overflow readback, sysfault, mmap, ioerr2/3/4/5/6, pointer-map fault, backup I/O state-machine, size-hint chunks, reservebytes, append VFS, VFS writer/locked-writer/sync/rollback-journal apply, lock-state/process-lock, WAL checkpoint/savepoint, and bigfile surfaces. This slice owns only `diskfull.test` full-disk recovery and result-normalization behavior.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsDiskfullDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsDiskfullDynamicTest.php` passed: `1 test files, 40814 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 3 assertions, 0 failures`.
- `php -r '$p="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` passed.
- `git diff --check -- lanes/libsqlite` passed.

Expected dashboard movement:

- Focused PASS growth: +1,203 TestRunner PASS lines.
- Behavior assertions: +40,814 focused assertions.
- Mapped coverage remains `1589 / 1589` because this is PASS-line growth over already mapped upstream corpus inventory.

Dependency closure:

- No new support component is required. The slice reuses the existing lane-local VFS I/O dynamic corpus model and hydrated upstream SQLite checkout as source truth.
