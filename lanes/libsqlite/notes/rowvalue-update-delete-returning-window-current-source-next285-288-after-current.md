# Row-value UPDATE/DELETE RETURNING window current-source next285-288 after-current

This slice prepares the next285-288 after-current current-source handoff for row-value `UPDATE`/`DELETE RETURNING` with window metadata.

- next285 records an after-current receipt from the accepted next284 seal plus retry change and current-source row counts.
- next286 builds an after-current ledger from yielded, attempted, and retried change counts with current-source row counts.
- next287 records retry-window row id and dense-rank coverage from the existing window metadata.
- next288 seals the next285-287 receipts and exposes a final readiness flag.

The slice reuses the existing row-value `UPDATE`/`DELETE RETURNING` executor, savepoint rollback/release model, and lane-local window metadata. It does not touch PRAGMA, VFS, WAL, pager, attach, JSON, planner, B-tree, encoding, trigger, or broad suite evidence surfaces.
