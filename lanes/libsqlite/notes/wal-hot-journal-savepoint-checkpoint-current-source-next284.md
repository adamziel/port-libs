# WAL hot-journal savepoint checkpoint current-source next284

Continues the after-ready WAL hot-journal savepoint checkpoint receipt chain after next283 with a WAL-index publish fence. It rejects receipts whose WAL-index salt is not synced and stays scoped away from suite, dashboard, SQL, JSON, B-tree, VFS, and planner work.
