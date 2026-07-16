# rowvalue-update-delete-returning-window-current-source-next252

- Added `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext252Plan` to layer row-number/high-water window fences over the accepted next248 publication cursor sequence.
- Focused behavior: current-source row-value `UPDATE`/`DELETE RETURNING` rows are numbered and completed before any next-source retry row becomes visible; held or unexpected yield acknowledgements keep retry rows out of the window.
- Application smoke: `examples/application-rowvalue-returning-window-current-source-next252.php` models copied `wp_options` cleanup/import publication after a savepoint retry.
- Dependency closure: no new support component needed; the slice reuses the native PHP row-value RETURNING executor, next245 ticket gate, and next248 publication cursor barrier.
- Non-overlap: avoids accepted next248 cursor barrier, next245 ticket gate, next244 transition windows, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces.
- Verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext252Test.php` passed with `1 test files, 60 assertions, 0 failures` and 60 focused PASS lines.
