Status: focused PHP corpus growth for WAL SHM read-mark restart current/next behavior.

Implementation:
- Added `SQLiteWal::restartReadMarkTransition()` to compose the current SHM checkpoint/read-mark state with restart/truncate checkpoint output.
- The transition preserves locked current readers when a pinned read mark blocks restart completion, drops invalid/unlocked marks for the next reader, resets released checkpoints to slot-0 database-only read marks, and distinguishes restarted WAL headers from truncated WAL sidecars.
- Added `SQLiteWalReadMarkRestartCurrentNext28Test.php` with 51 independent PASS cases.
- Added `application-wal-readmark-restart-current-next28.php` to smoke copied `wp_options` WAL restart diagnostics for a pinned current reader and a released next-reader restart.

Non-overlap:
This avoids accepted WAL SHM checkpoint restart corpus, WAL checkpoint transactions, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, VFS writer/sync/lock clusters, JSON table cursor/source/constraint work, B-tree page move/root-collapse/overflow clusters, SELECT SQL text/subquery/grouping/expression-order clusters, and Unicode GLOB work. The slice narrows to the transition between SHM current-reader read marks and the next reader after restart/truncate.

Dependency closure:
No new support component is needed. The slice reuses existing native PHP WAL parsing/checkpoint result code and SHM wal-index read-mark parsing.

Verification:
- `php -l lanes/libsqlite/src/SQLiteWal.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteWalReadMarkRestartCurrentNext28Test.php` -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-readmark-restart-current-next28.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReadMarkRestartCurrentNext28Test.php` -> 1 test files, 51 assertions, 0 failures, 51 PASS lines.
- `php lanes/libsqlite/examples/application-wal-readmark-restart-current-next28.php` -> printed pinned current-reader preserve-WAL status and released next-reader restart-WAL header sequence 29.
