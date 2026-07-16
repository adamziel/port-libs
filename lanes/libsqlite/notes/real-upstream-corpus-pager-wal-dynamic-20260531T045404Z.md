# real-upstream-corpus-pager-wal-dynamic-20260531T045404Z

Status: focused real-upstream pager/WAL corpus growth on accepted base
`d470482ec8f04bd52049cae518f9a06a2103fe0c`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walblock.test`
  sections `walblock-1.1.*` and `walblock-1.2.*`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walprotocol.test`
  sections `walprotocol-1.1..1.5` and `walprotocol-2.1..2.8`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walfault.test`
  sections `walfault-1` and `walfault-2`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pagerfault.test`
  sections `pagerfault-5.*`, `pagerfault-12.*`, `pagerfault-17.*`, and
  `pagerfault-21.*`.

Implemented test movement:

- Added `SQLiteRealUpstreamPagerWalDynamic20260531T045404ZTest.php`.
- The file contributes 1,000 distinct generated TestRunner behavior cases plus
  one source-citation case.
- Focused assertions: 33,001.
- Focused PASS lines: 1,001.

Behavior covered:

- Checksum and transaction recovery boundaries over valid, checksum-corrupt,
  salt-corrupt, and truncated WAL tails.
- Big-endian and little-endian WAL checksum modes.
- Committed-prefix truncation before checkpoint application.
- Reader snapshot metadata and multi-transaction current/next page visibility.
- Passive, full, restart, truncate, and noop checkpoint durability summaries.

Non-overlap:

- Avoids accepted pager/WAL dynamic batches for invalid page size, overwrite,
  noop checkpoint, mode/persist, fullsync, crash recovery, readonly SHM
  truncation, hash sidecar, rollback savepoint, lock recovery, warm-body, and
  previous 20260530/20260531 dynamic sweeps.
- This slice owns the walblock/walprotocol/walfault/pagerfault fault/protocol
  matrix for the current 20260531T045404Z lane and does not add production
  APIs, WordPress-specific names, generated fake upstream script IDs, or
  metadata-only admission rows.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531T045404ZTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531T045404ZTest.php`
  passed: `1 test files, 33001 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses lane-local WAL checksum,
  transaction recovery, reader snapshot, multi-transaction cluster, and
  checkpoint durability helpers.
