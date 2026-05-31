# real-upstream-corpus-pager-wal-dynamic-20260531T045823Z-0

Added `SQLiteRealUpstreamPagerWalCheckpointProtocolDynamic20260531Test.php` as
an additive real upstream pager/WAL corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal6.test`
  (`wal6-1.0..1.3`, `wal6-2.2..2.x`, `wal6-3.2..3.x`)
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal7.test`
  (`wal7-1.0..1.2`, `wal7-2.0`, `wal7-3.0`)
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal8.test`
  (`wal8-1.0`, `wal8-2.0`, `wal8-3.0`)
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walprotocol.test`
  (`walprotocol-1.1..1.5`, `walprotocol-2.1..2.8`)
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_walckpt.test`
  (`e_walckpt-3`, `e_walckpt-5`, `e_walckpt-6`)

The PHP batch generates 1,000 distinct WAL frame layouts across page sizes,
checkpoint modes, reader snapshot boundaries, committed transaction prefixes,
and uncommitted writer tails. Each case exercises existing WAL parsing,
transaction recovery, checkpoint plan/result, durable checkpoint result,
reader snapshot, and multi-transaction cluster behavior. A final source-section
case records the upstream coverage list.

Focused movement:

- `1001` TestRunner PASS cases
- `21001` focused assertions
- Mapped denominator rows unchanged; this is PASS-line/assertion growth over
  already mapped real upstream pager/WAL inventory.

Non-overlap:

This does not repeat the accepted pager/WAL warm-body, checksum/persist,
readonly-SHM, full-sync, lock-race, lock-recovery, hook/protocol, or prior
dynamic real-pager batches. It uses a fresh checkpoint/protocol matrix over
`wal6`, `wal7`, `wal8`, `walprotocol`, and `e_walckpt` sections.

Dependency closure:

No new support component is needed. The batch reuses existing native
`SQLiteWal`, `SQLiteWalHeader`, and `SQLiteWalMultiTransactionClusterPlan`
behavior.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalCheckpointProtocolDynamic20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalCheckpointProtocolDynamic20260531Test.php`
  - `1 test files, 21001 assertions, 0 failures`
- Source-specific API guard: not run; guard file is not present in this
  worktree.
- `git diff --check -- lanes/libsqlite`

Root harness: not run - isolated micro-slice.
