# Row-Value UPDATE/DELETE RETURNING Window Current Source next782-797

This slice extends the consolidated `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` continuation directly after the integrated next766-781 seal.

- `next782` records a handoff from `next781_ready`.
- `next783` audits current-source and next-source table hashes for the same retry rows.
- `next784` captures throughput preflight counters.
- `next785`, `next789`, `next793`, and `next797` seal each four-step block as ready.

The matching WordPress example uses copied `wp_options` rows and row-value UPDATE/DELETE RETURNING statements only; it does not add parser, executor, WAL/VFS, planner, B-tree, PRAGMA, trigger, or coordination-file coverage.
