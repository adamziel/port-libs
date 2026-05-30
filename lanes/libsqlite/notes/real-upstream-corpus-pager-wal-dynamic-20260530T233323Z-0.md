# real-upstream-corpus-pager-wal-dynamic-20260530T233323Z-0

Base accepted HEAD: `d7c5d7f50d0d0c3f24c91125036d23912559b628`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walpersist.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/waloverwrite.test`

Ported behavior:

- `walpersist-1.0..1.11`: persistent WAL file-control state keeps WAL and SHM sidecars after close.
- `walpersist-2.1..2.3`: persistent WAL plus non-negative `journal_size_limit` truncates the WAL to zero bytes on close while preserving integrity.
- `walpersist-3.1..3.4`: persistent WAL truncates after autocheckpoint close and reopens cleanly.
- `walpersist 4.1`: journal-mode toggle chain reports `truncate memory wal persist`.
- `waloverwrite 1.1/1.2 .1..6`: repeated updates to the same pages keep WAL frame count bounded and recover the last committed 799-byte image.
- `waloverwrite 1.1/1.2 .7..10`: savepoint rollback excludes rolled-back 797-byte updates and recovers the pre-savepoint 798-byte image.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalPersistOverwriteDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalPersistOverwriteDynamicTest.php`
- Result: `1 test files, 19507 assertions, 0 failures`; 1001 focused TestRunner PASS lines.

Non-overlap:

This slice does not touch accepted `wal2`, `walprotocol`, `walckptnoop`, WAL fullsync, checkpoint transaction, byte truncation, VFS writer, rollback-journal apply, or savepoint rollback writer clusters. It owns only the real upstream `walpersist.test` and `waloverwrite.test` persist/overwrite behavior cluster.

Dependency closure:

No new support component is required. The batch reuses the existing generic pager/WAL dynamic corpus plan infrastructure and records dependency labels for persistent WAL sidecars and WAL overwrite frame recovery.
