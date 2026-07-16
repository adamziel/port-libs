# Row-value UPDATE/DELETE RETURNING window current-source next281-284 after-current

This slice prepares the next281-284 after-current current-source handoff for row-value `UPDATE`/`DELETE RETURNING` with window metadata.

- next281 records a current-source receipt from the accepted next280 seal plus retry RETURNING and current-source row counts.
- next282 builds a returning-window ledger from yielded, suppressed, and retried RETURNING stream counts.
- next283 records retry-window coverage from the existing window rows.
- next284 seals the next281-283 receipts and exposes a final readiness flag.

The slice reuses the existing row-value `UPDATE`/`DELETE RETURNING` executor, savepoint rollback/release model, and lane-local window metadata. It does not touch PRAGMA, VFS, WAL, pager, attach, JSON, planner, B-tree, encoding, trigger, or broad suite evidence surfaces.
