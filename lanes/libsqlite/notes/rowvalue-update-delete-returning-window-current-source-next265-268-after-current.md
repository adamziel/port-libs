# Row-value UPDATE/DELETE RETURNING window current-source next265-268

Prepared after-current coverage for copied `wp_options` row-value `UPDATE`/`DELETE RETURNING` handoff after the next264 final receipt boundary.

- `next265` records a deterministic ledger over final current-source receipts.
- `next266` emits a source-epoch audit watermark over that ledger.
- `next267` partitions ledger rows into deterministic next-source handoff batches.
- `next268` publishes a final manifest proving final receipts, ledger rows, watermark, and batches agree.

The slice reuses the next261-264 current-source prerequisites and adds no broad suite, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, status, progress, dashboard, lane-status, supervisor, or private-file surface.
