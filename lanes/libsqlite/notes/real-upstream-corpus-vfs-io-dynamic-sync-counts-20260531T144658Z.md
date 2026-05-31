# real-upstream-corpus-vfs-io-dynamic-20260531T144658Z-0

Session: `port-dev-sqlite-yield-dyn-real-vfs-20260531T144658Z`

Base accepted HEAD: `a187757827b58c999a1fc6cda2f4be5e163b73e9`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/sync.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/sync2.test`
- Ported sections:
  - `sync-1.1`: main plus attached schema setup reports 8 syncs.
  - `sync-1.2`: attached `PRAGMA synchronous=ON` commit reports 9 syncs.
  - `sync-1.3`: attached `PRAGMA synchronous=FULL` commit reports 11 syncs.
  - `sync-1.4`: attached `PRAGMA synchronous=OFF` commit reports 0 syncs.
  - `sync2.test 1.1`: delete journal default/FULL transaction reports 4 syncs.
  - `sync2.test 1.2.3`: delete journal NORMAL reports 3 syncs.
  - `sync2.test 1.3.3`: delete journal OFF reports 0 syncs.
  - `sync2.test 1.4.3`: delete journal FULL reports 4 syncs.
  - `sync2.test 1.6`: WAL FULL first transaction reports 3 syncs.
  - `sync2.test 1.7`: WAL FULL subsequent transaction reports 1 sync.
  - `sync2.test 1.8.3`: WAL NORMAL subsequent transaction reports 0 syncs.
  - `sync2.test 1.9`: WAL checkpoint reports 2 syncs.
  - `sync2.test 1.10.3`: WAL OFF reports 0 syncs.
  - `sync2.test 1.11.1`: default WAL NORMAL first transaction reports 2 syncs.
  - `sync2.test 1.11.2`: default WAL NORMAL subsequent transaction reports 0 syncs.

## Patch

Added `SQLiteVfsIoDynamicPlan::syncPragmaTrafficProfile()` and
`SQLiteRealUpstreamCorpusVfsSyncDynamicTest.php`.

Focused growth:

- 1203 distinct TestRunner PASS cases.
- 21611 focused behavior assertions.
- Selected evidence moves from 2927535 to 2928738 pass / 0 fail.

Behavior covered:

- Delete-journal FULL, NORMAL, and OFF sync traffic.
- WAL FULL/NORMAL/OFF first and subsequent transaction sync traffic.
- WAL checkpoint sync targets.
- Attached multi-database commit sync traffic for ON, FULL, and OFF.
- Input guards for unsupported journal modes, synchronous modes, checkpoints,
  attached schema setup, row counts, and unsupported source rows.

## Non-overlap

This does not repeat accepted `io.test` sync matrix rows, VFS sync flag
planning, sync apply, rollback-journal apply, WAL checkpoint transactions,
lock-state/process-lock behavior, VFS file writers, or previously accepted
WAL byte-truncation and rollback-commit clusters. The owned upstream surface
is specifically `sync.test` and `sync2.test` VFS sync-count behavior.

## Dependency Closure

No new support component is required. The slice reuses the existing bounded
native `SQLiteVfsIoDynamicPlan` VFS corpus planner and adds a synchronous
pragma traffic profile inside that source file.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSyncDynamicTest.php`
  - red before implementation: `1 test files, 3 assertions, 1202 failures`
  - after implementation: `1 test files, 21611 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSyncDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSyncDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json OK\n";'`
  - `lane-status.json OK`
- `git diff --check -- lanes/libsqlite`
  - no output
