# WAL Hot-Journal Savepoint Checkpoint Current Source Next316-323

Prepared a larger follow-on after ready next312-315 for the WAL checkpoint/hot-journal current-source chain.

- `next316` verifies the WAL-index salt/frame range receipt after the ready checkpoint source is sealed.
- `next317` verifies reader-mark drain epoch receipts.
- `next318` verifies savepoint cache release epoch receipts.
- `next319` verifies hot-journal delete absence epoch receipts.
- `next320` verifies database header and page-cache receipt parity.
- `next321` verifies WAL-index reader reopen receipts.
- `next322` verifies the savepoint retry source receipt.
- `next323` seals the admitted next316-323 current-source chain.

The slice reuses the existing `afterCurrentCheckpoint()` receipt contract and does not touch suite/status/dashboard or unrelated SQL, JSON, B-tree, VFS, planner, or private state files.
