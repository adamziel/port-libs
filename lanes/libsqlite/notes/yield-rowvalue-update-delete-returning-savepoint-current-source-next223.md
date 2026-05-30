# Row-Value Yield RETURNING Savepoint Current Source Next223

- Slice: `rowvalue-update-delete-returning-savepoint-current-source-next223`.
- Behavior: row-value `UPDATE`/`DELETE ... RETURNING` rows yielded before `ROLLBACK TO` remain observable to the caller, while later attempted RETURNING rows are suppressed and retry statements read from the restored savepoint image.
- Application path: copied `wp_options` cleanup/import batches can stream early RETURNING rows to migration diagnostics, roll back a failed savepoint attempt, then retry without leaking rows from the discarded current source.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueYieldReturningSavepointCurrentSourceNextTest.php` passes with 71 assertions before full lane/root verification is intentionally left to the integrator.
- Dependency closure: no new support component is needed; this composes existing native row-value UPDATE/DELETE RETURNING execution and row-array savepoint images.
- Non-overlap: avoids accepted next218 rollback-to-current-source, next217 transaction OR ROLLBACK, next211 OR IGNORE, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters.
