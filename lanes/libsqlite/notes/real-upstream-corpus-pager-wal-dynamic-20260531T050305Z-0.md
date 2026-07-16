# real-upstream-corpus-pager-wal-dynamic-20260531T050305Z-0

Added focused real-upstream pager/WAL dynamic coverage for hydrated upstream SQLite `wal5.test`.

Owned upstream cluster:

- `wal5.test` section `2.4.*`: blocking checkpoint behavior across `PRAGMA wal_checkpoint` and `sqlite3_wal_checkpoint_v2`, including PASSIVE/TYPO/FULL/RESTART/TRUNCATE requests, reader/writer lock release sequencing, busy-handler return points, and checkpoint result triples.

Focused PHP coverage:

- New file: `lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalBlockingCheckpointDynamicTest.php`
- Distinct TestRunner cases: 1,010
- Behavior assertions: 27,876

Non-overlap:

- This does not repeat accepted WAL byte truncation, checkpoint transaction, rollback-journal apply/commit, VFS writer/sync/lock, `wal8` empty-open, `wal2` fullfsync, `wal3` readmark, or app-WAL slices. It owns the real upstream `wal5.test` `2.4.*` blocking-checkpoint matrix only.

Dependency closure:

- No new support component is needed. The slice reuses the existing lane-local `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal5BlockingCheckpointRows()` behavior model and the hydrated upstream SQLite checkout as source truth.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalBlockingCheckpointDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalBlockingCheckpointDynamicTest.php`
- `git diff --check -- lanes/libsqlite`
