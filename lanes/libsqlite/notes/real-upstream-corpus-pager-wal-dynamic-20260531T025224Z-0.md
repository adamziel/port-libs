# real-upstream-corpus-pager-wal-dynamic-20260531T025224Z-0

Session: `port-dev-sqlite-yield-dyn-real-pager-20260531T025224Z`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
- Ported `wal2.test` `wal2-13.*` database/WAL/SHM file-permission matrix.

Behavior ported:

- Adds `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal2FilePermissionRows()`.
- Adds 1,050 dynamic permission rows over the seven upstream permission cases from `wal2-13.*`.
- Each row verifies open admission, read admission, write rejection, sidecar-file state, generic setting payload provenance, and readonly write error routing.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalFilePermissionDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalFilePermissionDynamicTest.php`
- `git diff --check -- lanes/libsqlite`

Expected focused PASS growth:

- `+3152` TestRunner PASS cases from real upstream `wal2.test` `wal2-13.*`.

Non-overlap:

- This extends the real upstream pager/WAL corpus with WAL sidecar permission/open-read-write behavior.
- It does not repeat accepted WAL checkpoint fullfsync, WAL readmark/race rows, WAL readonly-SHM cache-spill, WAL hook/autocheckpoint, WAL persist/overwrite/restart, rollback-journal commit/apply, VFS writer/sync/lock, super-journal, grouped SELECT, JSON, B-tree, or source-neutral cleanup surfaces.
- Mapped denominator coverage remains complete at `1589 / 1589`; this is countable PHP PASS-line growth over already mapped real upstream WAL inventory.

Dependency closure:

- No new support component is needed. This reuses the lane-local pager/WAL corpus-plan structure and bounded file-permission/open-result modeling.
