# real-upstream-corpus-pager-wal-protocol-dynamic-20260531T060244Z-0

Base accepted HEAD: `5a0bbcc53e4d53b976a73e07fed57fd92e934f80`

Added `SQLiteRealUpstreamPagerWalProtocolLockingDynamicTest.php`, backed by the
hydrated upstream SQLite file:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walprotocol.test`

Upstream scenario ranges:

- `walprotocol.test` `1.1` and `1.2`: WAL recovery obtains the writer lock,
  then read-mark byte ranges, and releases them in the required order.
- `walprotocol.test` `1.3` and `1.4`: recovery returns `locking protocol`
  after repeated busy responses while acquiring reader-byte or writer-byte
  recovery locks.
- `walprotocol.test` `2.5` and `2.7`: a reentrant read opened from the
  recovery unlock callback sees the complete recovered row set.

Focused behavior count:

- 1000 dynamic TestRunner cases plus 2 source/non-overlap note cases.
- 27012 focused assertions.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php

php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalProtocolLockingDynamicTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalProtocolLockingDynamicTest.php

php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalProtocolLockingDynamicTest.php
1 test files, 27012 assertions, 0 failures
```

Expected dashboard movement: `phpPass +1002` from real focused PHP TestRunner
PASS cases. Mapped denominator coverage is already complete at `1589 / 1589`,
so this is PASS-line growth only.

Non-overlap: this does not add metadata-only admission rows and avoids accepted
WAL checkpoint byte materialization, VFS writer/sync/lock-state/process-lock,
rollback-journal apply/commit, `wal5` blocking checkpoint, `wal2` fullfsync,
`wal3` readmark, `wal8` empty-open, readonly-SHM, and prior snapshot-boundary
pager/WAL batches. It focuses only on `walprotocol.test` recovery locking and
reentrant-read protocol behavior.

Dependency closure: no new support component is needed. This reuses existing
bounded pager/WAL dynamic corpus modeling.
