# WAL hot-journal savepoint checkpoint current-source next285

Continues the after-ready WAL hot-journal savepoint checkpoint receipt chain after next284 with a reader-mark release fence. It rejects receipts that still retain reader marks and stays scoped away from suite, dashboard, SQL, JSON, B-tree, VFS, and planner work.
