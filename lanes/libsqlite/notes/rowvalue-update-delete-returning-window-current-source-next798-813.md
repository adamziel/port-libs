# Row-Value UPDATE/DELETE RETURNING Window Current Source next798-813

This slice extends the consolidated `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` continuation directly after the integrated next782-797 seal.

- `next798` records a handoff from `next797_ready`.
- `next799` audits current-source and next-source table hashes for the same retry rows.
- `next800` captures throughput preflight counters.
- `next801`, `next805`, `next809`, and `next813` seal each four-step block as ready.

The matching WordPress example uses copied `wp_options` rows and row-value UPDATE/DELETE RETURNING statements only; it does not add parser, executor, WAL/VFS, planner, B-tree, PRAGMA, trigger, or coordination-file coverage.
