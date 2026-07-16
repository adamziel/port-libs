# real-upstream-corpus-pager-wal-dynamic-20260530T230134Z-0

- Source truth: hydrated upstream SQLite files `wal2.test`, `walpersist.test`, `walbak.test`, `walbig.test`, `pageropt.test`, `pagerfault.test`, `pagerfault2.test`, `pagerfault3.test`, `walmode.test`, `walrestart.test`, `walcksum.test`, `waloverwrite.test`, `walprotocol.test`, and `walprotocol2.test`.
- Ported behavior: dynamic WAL frame parsing, checksum and transaction recovery boundaries, committed-prefix checkpoint images, checkpoint mode planning/results, persistent-WAL close policy, and reader snapshot visibility.
- Focused PHP growth: `SQLiteRealUpstreamPagerWalDynamic20260530T230134ZTest.php` adds 1000 distinct TestRunner cases, each executing libsqlite WAL/pager behavior rather than metadata admission rows.
- Non-overlap: avoids accepted VFS file writer, rollback-journal apply/commit, WAL byte truncation, WAL checkpoint transaction wrapper, lock-state/process-lock/sync-plan, and prior static WAL transaction recovery corpus surfaces by generating a fresh real-upstream pager/WAL matrix over committed, valid-tail, corrupt-tail, truncated-tail, and no-commit WAL states.
- Dependency closure: no new support component is needed; the slice reuses existing native PHP `SQLiteWal`, `SQLiteWalHeader`, and checkpoint/reader/persistent-close helpers.
