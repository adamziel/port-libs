# WAL hot-journal savepoint checkpoint current-source next280

Extends the after-ready WAL hot-journal savepoint checkpoint receipt chain after next279 with a generation fence. It rejects receipts whose commit generation no longer matches the admitted checkpoint current source and stays scoped away from suite, dashboard, SQL, JSON, B-tree, VFS, and planner work.
