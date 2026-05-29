# WAL hot-journal savepoint checkpoint current-source next286

Continues the after-ready WAL hot-journal savepoint checkpoint receipt chain after next285 with a page-cache digest carry fence. It rejects stale cache digests before the current-source window can advance and stays scoped away from suite, dashboard, SQL, JSON, B-tree, VFS, and planner work.
