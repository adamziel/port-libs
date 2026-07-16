# real-upstream-corpus-pager-wal-dynamic-20260531T012140Z-0

Scope: real upstream pager/WAL dynamic corpus coverage from hydrated SQLite upstream files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walpersist.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walrestart.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walckptnoop.test`
- Existing `SQLitePagerWalDynamicPlan` source cites `pager1.test pager1-1.*` for multiclient locking.

Added `SQLiteRealUpstreamCorpusPagerWalDynamic20260531Test.php` with 1000 focused generated PASS cases over journal-mode transition and multiclient reader/writer visibility rows, plus upstream source citation, malformed-input guards, and a non-overlap/dependency-closure assertion.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531Test.php` -> no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531Test.php` -> `1 test files, 12021 assertions, 0 failures`

Non-overlap: avoids accepted WAL byte truncation, checkpoint transaction, rollback journal apply/commit, VFS sync/file writer, pager late-WAL2, and app-WAL slices. This slice exercises existing generic pager/WAL dynamic state behavior against real hydrated upstream pager/WAL script sections.

Dependency closure: no new support component needed; reuses existing generic `SQLitePagerWalDynamicPlan` and hydrated upstream SQLite test files as source truth.
