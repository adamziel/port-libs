# real-upstream-corpus-vfs-io-dynamic-20260530T200738Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/avfs.test`
- Ported scenarios: `avfs-4.1`, `avfs-4.2`, and `avfs-4.3`.

## Coverage

- Added `SQLiteVfsIoDynamicPlan::appendShellLifecycleProfile()` for appendvfs shell lifecycle behavior:
  non-empty appendee alignment, empty appendee offset zero, archive/fallback table output, update/reopen row visibility, trailer magic, and prefix preservation.
- Added two focused TestRunner PASS cases to `SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`.
- Added assertion delta: `16644` new behavior assertions from a 640-case real upstream parameter matrix plus malformed-input guards.
- Non-overlap: this extends existing VFS/IO real-corpus coverage into `avfs.test` section 4 shell append/update behavior. It does not repeat existing `avfs-1`, `avfs-2`, `avfs-3`, `avfs-5`, `io.test`, `ioerr*`, `cksumvfs`, `walvfs`, pagerfault, VFS writer, sync, lock, rollback-journal, or WAL checkpoint clusters.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` -> `1 test files, 62066 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native VFS/IO dynamic planner surface and adds bounded appendvfs lifecycle behavior from hydrated upstream `avfs.test`.
