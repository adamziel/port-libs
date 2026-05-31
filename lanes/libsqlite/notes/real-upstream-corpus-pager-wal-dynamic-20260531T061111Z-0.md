# real-upstream-corpus-pager-wal-dynamic-20260531T061111Z-0

- Base accepted HEAD: `cd24ba2f7b741bb89ced6cb6c27264084794565b`.
- Added `SQLiteRealUpstreamPagerWalDynamic20260531T061111ZTest.php` with 1000 dynamic pager/WAL cases plus one upstream-section receipt case.
- Hydrated upstream source files cited: `wal.test` (`wal-1.*`, `wal-2.*`, `wal-3.*`, `wal-4.*`), `walckpt.test` (`walckpt-2.*`, `walckpt-3.*`), `walrestart.test` (`walrestart-1.*`, `walrestart-2.*`), `wal2.test` (`wal2-11.*`), and `pager1.test` (`pager1-24.*`).
- Behavior covered: committed-prefix recovery after valid/corrupt/partial WAL tails, reader snapshot page-source mapping, checkpoint mode/durable result agreement, savepoint rollback WAL byte truncation, committed transaction boundaries, and current/next recovery page-source handoff.
- Focused verification: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531T061111ZTest.php` -> `1 test files, 35001 assertions, 0 failures`, `1001` PASS lines.
- Expected dashboard movement: `phpPass +1001` if accepted without overlap; mapped denominator remains `1589 / 1589`.
- Non-overlap: this slice does not touch production source, JSON, B-tree, VFS writer/lock/sync, SELECT SQL text, PRAGMA, UPSERT, or source-neutral API surfaces. It adds a new timestamped pager/WAL dynamic corpus file and avoids the accepted `20260531T054002Z` test by using a different frame shape, savepoint rollback plan, reader page-map assertions, and upstream section receipt.
- Dependency closure: no new support component is needed; existing native `SQLiteWal` and `SQLiteSavepointStack` behavior is reused.
