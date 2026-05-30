real-upstream-corpus-pager-wal-dynamic-20260530T233745Z

Base accepted HEAD: e26da88382a9c31477121cff98ca70bfba38b5f3

Implemented a focused real-upstream pager/WAL corpus batch in
SQLiteRealUpstreamPagerWalDynamicRealPager20260530T233745ZTest.php.

Source truth:
- /home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test: wal-2.*
- /home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test: wal2-1.*, wal2-2.*, wal2-10.*
- /home/claude/port-libs/.upstream-cache/libsqlite/test/walrestart.test: walrestart-1.*
- /home/claude/port-libs/.upstream-cache/libsqlite/test/walpersist.test: walpersist-1.*
- /home/claude/port-libs/.upstream-cache/libsqlite/test/walmode.test: walmode-4.*
- /home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test: pager1-9.*, pager1-12.*
- /home/claude/port-libs/.upstream-cache/libsqlite/test/pager2.test: pager2-2.*

Focused behavior:
- 1000 generated cases build valid WAL byte streams with two committed
  transactions and one valid uncommitted writer tail.
- Each case verifies transactionRecoveryBoundary() keeps the committed prefix,
  drops the uncommitted tail, exposes two committed transactions, and keeps
  reader/checkpoint/durable checkpoint metadata aligned across page sizes and
  checkpoint modes.
- The source-section sentinel records the ten hydrated upstream sections used
  by the batch.

Verification:
- php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicRealPager20260530T233745ZTest.php
- php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicRealPager20260530T233745ZTest.php
  Result: 1 test files, 21001 assertions, 0 failures, 1001 PASS lines.

Dashboard expectation:
- phpPass +1001, from 1158670 to 1159671.
- phpFail remains 0.
- mapped denominator remains 1589 / 1589 because this is behavior PASS-line
  growth over already mapped upstream pager/WAL files.

Dependency closure:
- No new support component is needed. The batch reuses native PHP
  SQLiteWal, SQLiteWalHeader, and SQLiteWalMultiTransactionClusterPlan.
