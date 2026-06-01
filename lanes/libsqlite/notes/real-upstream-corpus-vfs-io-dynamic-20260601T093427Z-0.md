# real-upstream-corpus-vfs-io-dynamic-20260601T093427Z-0

## Source truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/sync2.test`.
- Ported behavior: default `SQLITE_DEFAULT_SYNCHRONOUS=2` and `SQLITE_DEFAULT_WAL_SYNCHRONOUS=1` sync transitions across WAL checkpoint, WAL transactions after checkpoint, delete-journal mode, `synchronous=OFF`, and close/reopen default restoration.
- Covered upstream sections: `sync2.test` 1.12, 1.13.1, 1.13.2, 1.14, 1.15.1, 1.15.2, 1.15.3, 1.16, 1.17.1, 1.17.2, 1.17.3, 1.18, 1.19.1, 1.19.2, 1.19.3, 1.20.1, 1.20.2, and 1.20.3.

## Patch delta

- Extended `SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile()` with a bounded `wal_restart_after_checkpoint` branch for `sync2.test` 1.13.1, plus WAL checkpoint result metadata for default-sync transition checks.
- Added upstream scenario mapping for `sync2.test` 1.12 through 1.20 default synchronous/WAL synchronous sections.
- Added `SQLiteRealUpstreamCorpusVfsSyncDefaultTransitionDynamicTest.php` with 1,012 dynamic behavior cases plus source-truth, malformed-input, non-overlap, and dependency-closure checks.
- Added `application-vfs-sync-default-transition.php` as a generic application smoke for WAL checkpoint, first WAL transaction after checkpoint, and reopened delete-journal default FULL sync behavior.
- Updated `lane-status.json` by the verified +1,015 focused TestRunner PASS-case delta, from 5,791,113 to 5,792,128.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSyncDefaultTransitionDynamicTest.php` passed.
- `php -l lanes/libsqlite/examples/application-vfs-sync-default-transition.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSyncDefaultTransitionDynamicTest.php` passed: `1 test files, 25329 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSyncDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSyncDefaultTransitionDynamicTest.php` passed: `2 test files, 46940 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-vfs-sync-default-transition.php --self-test` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 5 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` passed.
- `git diff --check -- lanes/libsqlite` passed.

## Non-overlap

This slice owns only upstream `sync2.test` 1.12-1.20 default synchronous and default WAL synchronous sync-transition behavior. It avoids accepted `io.test` sync/device/default-page-size/cache-retention batches, VFS sync flag planning/apply, rollback-journal apply/commit, WAL checkpoint transaction planning, VFS file writer/locked writer/process locks, lock-state, win32 lock retry, mmap, reservebytes, ioerr, pagerfault, B-tree, JSON, and SELECT clusters.

## Dependency closure

No new support component is needed. The batch reuses `SQLiteVfsIoDynamicPlan` and the hydrated upstream SQLite `sync2.test` source file.
