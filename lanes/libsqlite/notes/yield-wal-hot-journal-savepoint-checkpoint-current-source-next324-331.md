# WAL Hot-Journal Savepoint Checkpoint Current Source Next324-331

Prepared a larger follow-on after ready next316-323 for the WAL checkpoint/hot-journal current-source chain.

- `next324` verifies the WAL-index generation receipt after the ready checkpoint source is sealed.
- `next325` verifies reader reopen epoch receipts.
- `next326` verifies savepoint release source receipts.
- `next327` verifies hot-journal delete source receipts.
- `next328` verifies database page-cache generation receipts.
- `next329` verifies WAL frame reader boundary receipts.
- `next330` verifies savepoint retry hot-journal absence receipts.
- `next331` seals the admitted next324-331 current-source chain.

The slice reuses the existing `afterCurrentCheckpoint()` receipt contract and stays out of suite/status/dashboard files, unrelated SQL, JSON, B-tree, VFS, planner, and private state.
