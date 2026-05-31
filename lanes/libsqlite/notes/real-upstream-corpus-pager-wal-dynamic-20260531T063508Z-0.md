# real-upstream-corpus-pager-wal-dynamic-20260531T063508Z-0

Ported a real upstream pager/WAL readonly-SHM refresh cluster from the hydrated SQLite checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walro2.test`
- upstream sections: `walro2-1.1.2`, `walro2-1.2.2`, `walro2-2.2`, `walro2-2.3.3`, `walro2-3.1.1`, `walro2-3.2.1`, `walro2-3.3.1`, `walro2-3.3.3`, `walro2-4.1.1`, `walro2-4.1.3`

New focused coverage within the existing readonly-SHM refresh test file:

- `lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalReadonlyShmRefreshDynamicTest.php`
- 1,920 dynamic upstream rows plus 2 metadata/dependency tests
- Full focused file result after preserving existing coverage: `1 test files, 57963 assertions, 0 failures`
- PASS-line delta: `+1922`

Non-overlap:

- Avoids accepted WAL byte truncation, checkpoint transaction, rollback journal apply/commit, VFS sync/file writer/lock, page relocation, JSON table cursor/source/constraint batches, and prior pager/WAL `walro.test` readonly-SHM cache-spill rows.
- This slice specifically covers `walro2.test` readonly copied WAL/SHM open, zeroed SHM recovery, truncate-checkpoint refresh, WAL growth/wrap recovery, and readonly cache flush behavior.

Dependency closure:

- No new support component needed.
- Reuses `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walReadonlyShmRefreshRows()` and existing generic in-memory row fixtures.
