# real-upstream-corpus-vfs-io-dynamic-20260531T112510Z-0

Session: `port-dev-sqlite-yield-dyn-real-vfs-20260531T112510Z`

Base accepted HEAD: `729105b48b26aa61ef0db4b008592ded7b7410d2`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/superlock.test`
- Ported sections:
  - `superlock-1`: rollback database superlock excludes ordinary clients.
  - `superlock-2`: WAL database superlock excludes ordinary clients.
  - `superlock-3`: checkpoint attempts are blocked while the WAL database is superlocked.
  - `superlock-4`: busy-handler acquisition attempts fail with `SQLITE_BUSY` when an existing client blocks the superlock.
  - `superlock-6`: WAL-index state is recovered after superlocked database-image swaps.

## Patch

Added native superlock profiling to `SQLiteVfsIoDynamicPlan` and covered it with
`SQLiteRealUpstreamCorpusVfsSuperlockDynamicTest.php`.

Focused growth:

- 1700 distinct TestRunner PASS cases.
- 26161 focused behavior assertions.
- Selected evidence moves from 2893069 to 2894769 pass / 0 fail.

Behavior covered:

- Rollback and WAL superlock acquisition both block read and write clients.
- WAL superlocks block checkpoint activity until the superlock is released.
- Busy-handler plans preserve callback-attempt order and finish with
  `SQLITE_BUSY` when an existing connection prevents superlock acquisition.
- Superlocked database-image swaps force WAL-index rebuild and preserve the
  post-recovery snapshot, with page-size-change variants included.

## Non-overlap

This does not repeat accepted VFS lock byte ranges, VFS lock-state/process
locks, VFS file writer/sync/rollback/locked-writer apply, WAL checkpoint
transactions, WAL byte truncation, rollback-journal commit/apply, super-journal
commit, JSON table SELECT/cursor/constraint batches, B-tree page relocation,
or the earlier `io.test`, `filectrl.test`, `appendvfs.test`, `walvfs.test`,
`avfs.test`, and crash/hot-journal VFS corpus batches. The owned upstream
surface is specifically `superlock.test` superlock exclusion and recovery
semantics.

## Dependency Closure

No new support component is required. The slice reuses the existing bounded
native `SQLiteVfsIoDynamicPlan` VFS corpus planner and adds a superlock profile
inside that source file.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSuperlockDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSuperlockDynamicTest.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSuperlockDynamicTest.php`
  - `1 test files, 26161 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json OK\n";'`
  - `lane-status.json OK`
- `git diff --check -- lanes/libsqlite`
  - no output
