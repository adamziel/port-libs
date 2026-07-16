# real-upstream-corpus-pager-wal-dynamic-20260530T211327Z-0

Added `SQLiteRealUpstreamPagerWalMultiTransactionDynamicTest.php`, an additive
real upstream pager/WAL corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager4.test`

Ported upstream scenario ranges:

- `wal2.test` `wal2-1.*`, `wal2-2.*`, `wal2-6.*`, and `wal2-10.*`
- `wal3.test` `wal3-2.*` and `wal3-6.*`
- `pager3.test` `pager3-1.*`
- `pager4.test` `pager4-1.*`

Focused behavior:

- WAL frame checksum parsing over 32 dynamic page-size/scenario combinations.
- Multi-transaction commit grouping and per-cluster frame/page accounting.
- Duplicate page writes within one transaction and superseded-frame reporting.
- Current-reader snapshot boundaries before later committed growth.
- Next-reader checkpoint database materialization after the final commit.
- Restart checkpoint invariants that preserve uncommitted tail frames without
  applying them to the checkpointed database image.

Focused verification:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalMultiTransactionDynamicTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalMultiTransactionDynamicTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalMultiTransactionDynamicTest.php
1 test files, 2129 assertions, 0 failures
```

Expected dashboard movement: `phpPass +2129` if the integrator admits this new
focused TestRunner file. Mapped denominator coverage remains unchanged because
the lane already reports complete mapped inventory; this is PASS-line growth
from real upstream pager/WAL behavior, not metadata admission.

Non-overlap: this avoids the accepted pager/WAL snapshot-boundary corpus,
persist-mode/read-only checkpoint tests, WAL byte truncation, rollback-journal
apply/commit, checkpoint transaction, VFS writer/sync/lock work, and numbered
pager current-source helper families. The new surface is transaction-cluster
shape and current/next reader visibility over `wal2`, `wal3`, `pager3`, and
`pager4` upstream behavior.

Dependency closure: no new support component is needed. The slice reuses
existing native PHP `SQLiteWal`, `SQLiteWalHeader`, and
`SQLiteWalMultiTransactionClusterPlan` behavior.
