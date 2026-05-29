# Row-Value UPDATE/DELETE RETURNING Window Current Source next766-781

This slice extends the consolidated `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` continuation directly after the integrated next750-765 seal.

- `next766` records a handoff from `next765_ready`.
- `next767` audits current-source and next-source table hashes for the same retry rows.
- `next768` captures throughput preflight counters.
- `next769`, `next773`, `next777`, and `next781` seal each four-step block as ready.

The matching WordPress example uses copied `wp_options` rows and row-value UPDATE/DELETE RETURNING statements only; it does not add parser, executor, WAL/VFS, planner, B-tree, PRAGMA, trigger, or coordination-file coverage.
