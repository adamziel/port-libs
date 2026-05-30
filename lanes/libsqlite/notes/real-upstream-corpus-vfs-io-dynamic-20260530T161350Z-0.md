# Real upstream corpus VFS IO dynamic

Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260530T161350Z-0`

Accepted base: `8bf0d9f81b29a5601901bb34dfd730670ed39bbc`

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/avfs.test`
  - `avfs-1.0` through `avfs-1.4`: appendvfs database offset, reopen, and prefix-preservation behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
  - `io-2.*`, `io-3.*`, and `io-4.*`: atomic-write, sequential, and safe-append IO traffic changes.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/nolock.test`
  - `nolock-1.0` through `nolock-2.22`: `nolock=1` and `immutable=1` suppress VFS lock/access calls.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/filectrl.test`
  - `filectrl-1.1` through `filectrl-1.6`: file-control probing and temp filename behavior.

## Ported behavior

Added `SQLiteVfsIoDynamicPlan`, a generic VFS IO behavior helper that composes existing open, capability, and file-control primitives for:

- appendvfs page-aligned appended database offsets and trailer offset metadata;
- device-characteristic IO traffic summaries for atomic, sequential, safe-append, WAL, rollback, and sync-off paths;
- URI `nolock=1` and `immutable=1` lock/access-call suppression;
- SQL-level file-control sequencing for PRAGMA and `file_control(...)` inputs.

Added `SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` with `12` focused TestRunner PASS cases and `973` assertions. The assertions are non-overlapping with the accepted VFS file writer, lock-state, lock-byte, sync-plan, rollback-journal apply, and file-control persistence clusters because this slice targets the upstream appendvfs/io/nolock/filectrl behavior matrix rather than persistence sidecars or real write application.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`
  - `1 test files, 973 assertions, 0 failures`

Dependency closure: no new support component is needed. The slice reuses existing bounded native PHP components `SQLiteOpenPlan`, `SQLiteVfsCapabilityPlan`, and `SQLiteVfsFileControlState`.
