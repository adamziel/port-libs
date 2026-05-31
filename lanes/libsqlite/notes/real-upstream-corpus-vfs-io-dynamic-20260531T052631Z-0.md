# real-upstream-corpus-vfs-io-dynamic-20260531T052631Z-0

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/cksumvfs.test`
- Sections: `cksumvfs.test` 1.0 through 1.9.
- Behavior cluster: checksum VFS reserve-byte page layout survives create/select/delete, a large randomblob transaction, WAL-mode delete, checkpoint backfill, recursive reload insert, saved-image reopen, and direct reopen.

## Local changes

- Added `SQLiteVfsIoDynamicPlan::checksumVfsReserveProfile()` for reserve-byte checksum VFS WAL/reopen lifecycle modeling.
- Added `SQLiteRealUpstreamCorpusVfsIoDynamicCksumReserve20260531Test.php` with 2500 generated real-upstream behavior cases plus citation, malformed-input, and non-overlap/dependency assertions.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicCksumReserve20260531Test.php` - no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicCksumReserve20260531Test.php` - `1 test files, 72511 assertions, 0 failures`.

Focused PASS-line delta: 2504 PASS cases. Local selected `phpPass` moved from `2297185` to `2299689` pending clean integration.

## Non-overlap

This slice covers `cksumvfs.test` reserve-byte checksum VFS WAL checkpoint/reopen behavior. It avoids accepted `io.test` atomic/default-page/cache-spill/short-name clusters, rollback-journal apply/commit, VFS writer/sync/lock, WAL byte truncation, pager recovery, and prior `ioerr6` fault recovery batches.

## Dependency closure

No new support component is needed. The slice reuses the existing `SQLiteVfsIoDynamicPlan` arithmetic-profile pattern and hydrated upstream `cksumvfs.test` source truth.
