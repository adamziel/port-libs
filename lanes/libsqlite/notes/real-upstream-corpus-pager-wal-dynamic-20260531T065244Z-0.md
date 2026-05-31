# Real upstream corpus pager/WAL dynamic 20260531T065244Z-0

Status: focused real-upstream pager/WAL behavior growth.

Upstream source files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walro.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walro2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walnoshm.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walshared.test`

Ported scenario ranges:

- `walro.test` `1.1.1`, `1.1.3`, `1.1.5`, `1.1.7`, `1.2.3`, `1.4.2`, and `1.4.4`
- `walro2.test` page-size and zero-SHM refresh cases around `1.*.1.2`, `1.*.2.3`, `2.*.3.2`, and `2.*.3.3`
- `walnoshm.test` `1.4`, `1.7`, `2.1.5`, and `2.2.2`
- `walshared.test` `1.2`, `1.3`, and `1.4`

The PHP test builds valid WAL byte streams from those real upstream behavior classes and checks transaction recovery boundaries, committed-prefix truncation, readonly reader snapshots, pinned-checkpoint behavior, released-checkpoint behavior, and shared-cache checkpoint blocking through existing native PHP WAL primitives.

Focused count: 1,001 distinct TestRunner PASS cases, with 1,000 dynamic rows plus one upstream provenance row.

Non-overlap: this avoids accepted `wal2`, `wal3`, `walckpt`, `walrestart`, `walcrash`, `walpersist`, `waloverwrite`, readonly-SHM refresh, WAL savepoint byte truncation, VFS writer/sync/lock/rollback, rollback-journal commit/super-journal, and pager master-journal numbered surfaces. The new coverage is the readonly/no-SHM/shared-cache WAL boundary modeled from `walro*`, `walnoshm`, and `walshared`.

Dependency closure: no new support component is needed. This reuses lane-local `SQLiteWal`, `SQLiteWalHeader`, and `SQLiteWalMultiTransactionClusterPlan` primitives.
