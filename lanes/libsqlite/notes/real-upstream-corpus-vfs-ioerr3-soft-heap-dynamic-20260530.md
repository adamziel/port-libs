# Real Upstream Corpus VFS ioerr3 Soft Heap Dynamic

- Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T185936Z-0`
- Base accepted HEAD: `49b5c4e4a088c53e02910590cc011ce37a3ffc52`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr3.test`
- Upstream scenarios: `ioerr3.test ioerr3-1` and `ioerr3.test ioerr3-2`

This slice adds a non-overlapping VFS I/O dynamic cluster for `ioerr3.test`.
It does not repeat accepted `io.test`, `avfs.test`, `ioerr2.test`,
`ioerr5.test`, `ioerr6.test`, `pagerfault.test`, default-page-size,
atomic-write, reopen-fault, or VFS writer coverage.

The new behavior models soft-heap-limit/cache-pressure I/O error recovery:
dynamic soft heap limits, cache page settings, row counts, row payload sizes,
fault indexes, transaction rollback state, temp table behavior, memory reclaim
attempts, pager error state, integrity preservation, and open-file cleanup.

Focused verification:

```text
php -l lanes/libsqlite/src/SQLiteVfsIoTrafficPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteVfsIoTrafficPlan.php

php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerr3SoftHeapDynamicTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerr3SoftHeapDynamicTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerr3SoftHeapDynamicTest.php
1 test files, 20345 assertions, 0 failures
```

Selected PASS-line growth: `+1027` focused TestRunner PASS cases.
Mapped denominator movement: none; this is behavior/PASS-line growth inside
the already mapped VFS I/O family.

Dependency closure: no new support component is needed. The slice reuses the
existing bounded VFS I/O traffic planner and adds only lane-local PHP behavior.
