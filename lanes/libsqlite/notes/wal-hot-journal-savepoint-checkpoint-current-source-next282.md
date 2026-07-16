# WAL hot-journal savepoint checkpoint current-source next282

Extends the after-ready WAL hot-journal savepoint checkpoint receipt chain after next281 with a page-cache digest fence. It rejects stale cache receipts before the checkpoint current source can be sealed and stays scoped away from suite, dashboard, SQL, JSON, B-tree, VFS, and planner work.
