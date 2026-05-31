# real-upstream-corpus-pager-wal-dynamic-20260531T002101Z-0

Slice: `real-upstream-corpus-pager-wal-dynamic-20260531T002101Z-0`

Status: ready for integration.

Upstream source files and sections:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal3.test`: `wal3-1.*`, `wal3-2.*`, `wal3-5.*`, `wal3-6.*`, `wal3-9.*`, `wal3-10.*`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal4.test`: `wal4-1.*`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal6.test`: `wal6-1.*`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal7.test`: `wal7-1.*`, `wal7-2.*`, `wal7-3.*`, `wal7-4.*`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager3.test`: `pager3-1.*`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager4.test`: `pager4-1.1`

Implementation:

- Added `SQLiteRealUpstreamPagerWalDynamic20260531T002101ZTest.php` with 1,000 real upstream pager/WAL dynamic behavior cases plus a source-section guard.
- The cases build valid WAL byte streams with two committed transactions and clean, valid-tail, checksum-corrupt-tail, and truncated-tail variants, then assert transaction recovery, checkpoint planning/result parity, reader snapshots, committed transaction grouping, and persistent WAL close decisions.
- No production source was changed. Existing `SQLiteWal` behavior is reused.

Non-overlap:

- This does not repeat the accepted `walckptnoop.test`, `wal2.test`, WAL hash-sidecar, WAL mode-lock, WAL persist-limit, checkpoint transaction, byte-truncation, rollback-journal apply, or VFS writer/sync clusters.
- Countable growth is focused PASS-line growth: 1,001 TestRunner cases from real upstream pager/WAL sections, including 1,000 behavior cases and one source-section guard.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP WAL parser, checksum recovery, checkpoint, reader snapshot, and persistent close helpers.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531T002101ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531T002101ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
