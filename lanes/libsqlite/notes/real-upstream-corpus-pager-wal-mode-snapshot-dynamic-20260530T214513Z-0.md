# Real upstream pager/WAL mode snapshot dynamic corpus

Micro-slice: `real-upstream-corpus-pager-wal-dynamic-20260530T214513Z-0`

Accepted base: `551608c47b9b5c9b4c74afdd6349b99f03720fcd`

This slice adds `SQLiteRealUpstreamPagerWalModeSnapshotDynamicTest.php`, a
test-only high-yield real upstream pager/WAL batch over hydrated SQLite tests:

- `wal6.test` `wal6-1.0.*` through `wal6-1.3.*`, `wal6-2.1` through
  `wal6-2.5`, `wal6-3.2`, `wal6-4.1` through `wal6-4.4`, and
  `wal6-5.1` through `wal6-5.2`
- `wal7.test` `wal7-1.0` through `wal7-4.0`
- `pager3.test` `pager3-1.*`
- `pager4.test` `pager4-1.1` through `pager4-1.11`

Focused behavior:

- WAL journal-mode transition snapshots across DELETE/PERSIST/TRUNCATE/MEMORY/OFF.
- BUSY snapshot reader boundaries for PASSIVE/RESTART/TRUNCATE checkpoints.
- Recovery of committed WAL prefixes while discarding draft writer frames.
- Checkpoint page-image alignment and reader snapshot page lookup.
- Corrupt and truncated WAL tail classification after a committed prefix.
- Pager journal persistence expectations for rollback journal modes.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalModeSnapshotDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalModeSnapshotDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalModeSnapshotDynamicTest.php`
  - `1 test files, 35953 assertions, 0 failures`

Countability:

- Adds 11,581 distinct focused TestRunner PASS cases.
- Adds 35,953 behavior assertions.
- Expected `phpPass` movement: `782971 -> 794552`.
- Mapped denominator remains `1589 / 1589`; this is behavior PASS growth over
  already hydrated real upstream pager/WAL files, not new denominator mapping.

Non-overlap:

- Avoids the accepted `real-upstream-corpus-pager-wal-snapshot-boundary`
  coverage over `walrestart.test`, `walshared.test`, `walpersist.test`,
  `wal5.test`, and `pager2.test`.
- Avoids the accepted warm-body `wal.test` coverage, WAL byte truncation,
  rollback-journal apply/commit, super-journal commit, checkpoint transaction,
  VFS writer/sync/lock, and pager master-journal reader-cache wrapper families.

Dependency closure:

- No new support component is needed. The batch reuses existing native PHP
  `SQLiteWal`, `SQLiteWalHeader`, reader snapshot, checkpoint mode, and
  transaction recovery primitives.
