# Real upstream pager/WAL read-mark matrix dynamic corpus

Micro-slice: `real-upstream-corpus-pager-wal-dynamic-20260530T224926Z-0`

Base accepted HEAD: `dc9a740fd34e07dba61e9143b3604d183ad170bf`

Added `SQLiteRealUpstreamPagerWalReadMarkMatrixDynamicTest.php`, a focused
behavior corpus backed by the hydrated upstream SQLite checkout under
`/home/claude/port-libs/.upstream-cache/libsqlite/test`.

Upstream source files and scenario ranges:

- `wal3.test` `wal3-2.*`: reader-blocked checkpoint matrix.
- `wal3.test` `wal3-6.*`: restart after fully checkpointed WAL.
- `wal2.test` `wal2-6.*`: read-mark and lock lifecycle.
- `wal2.test` `wal2-13.*`: checkpoint-fullfsync reader visibility.
- `walshared.test` `walshared-1.0` through `walshared-1.4`: shared-cache read
  transaction snapshots.

Behavior covered:

- 200 dynamic WAL scenarios over page sizes 512, 1024, 2048, and 4096.
- Big-endian and little-endian WAL checksums.
- Latest reader snapshots through committed frame boundaries with an
  uncommitted tail.
- Passive/full/restart/truncate/noop checkpoint plans and results with pinned
  readers.
- Read-mark slot reuse and checkpoint pinning over stale/current readers.
- Corrupt-tail transaction recovery preserving the committed prefix.
- Reader visibility before and after checkpoint-side WAL preservation/reset
  decisions.

Focused verification:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalReadMarkMatrixDynamicTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalReadMarkMatrixDynamicTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalReadMarkMatrixDynamicTest.php
1 test files, 4001 assertions, 0 failures
```

Expected dashboard movement: `phpPass +1001` from real focused PHP TestRunner
PASS cases. Mapped denominator coverage is already complete at `1589 / 1589`,
so this is PASS-line growth only.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP `SQLiteWal`, `SQLiteWalHeader`, checkpoint, read-mark, and
transaction-recovery helpers.

Non-overlap: this does not add metadata-only admission rows and avoids accepted
pager/WAL persist-mode, overwrite, noop-checkpoint, crash-recovery,
restart-overwrite, WAL byte truncation, rollback-journal apply, checkpoint
transaction, savepoint rollback, VFS writer/sync/lock clusters, and prior
snapshot-boundary coverage. It focuses on read-mark/checkpoint matrix behavior
from `wal2`, `wal3`, and `walshared` scenario ranges.
