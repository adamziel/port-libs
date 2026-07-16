# WAL hot-journal savepoint checkpoint current-source next281

Extends the after-ready WAL hot-journal savepoint checkpoint receipt chain after next280 with a schema-cookie fence. It rejects receipts whose schema cookie no longer matches the admitted checkpoint current source and stays scoped away from suite, dashboard, SQL, JSON, B-tree, VFS, and planner work.
