# real-upstream-corpus-vfs-io-dynamic-20260531T020946Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/journal3.test`
- Scenarios: `journal3-1.1` and `journal3-1.2.1` through `journal3-1.2.4`.

Behavior ported:

- Added `SQLiteVfsIoDynamicPlan::rollbackJournalPermissionProfile()` for the
  upstream rollback-journal permission inheritance contract: when atomic batch
  write is not used and the platform exposes Unix permissions, the rollback
  journal is absent before the transaction, created during the transaction with
  the same permission mode as the database file, and removed cleanly after
  rollback while integrity remains `ok`.
- Added `SQLiteRealUpstreamCorpusVfsJournal3PermissionDynamicTest.php` with
  1,000 distinct dynamic TestRunner PASS cases over the upstream permission
  modes `00644`, `00666`, `00600`, and `00755`, plus source-citation,
  upstream guard, malformed-input, and case-volume checks.

Non-overlap:

- This avoids accepted VFS file writer, locked writer, process locks,
  lock-state, sync plan/apply, rollback-journal apply/commit, super-journal,
  WAL checkpoint/savepoint byte truncation, `avfs.test`, `io.test`,
  `ioerr*.test`, `memjournal*.test`, `subjournal.test`, `cksumvfs.test`, and
  `walvfs.test` dynamic batches.
- Existing pager recovery coverage mentions `journal3.test` rollback-journal
  creation/removal; this slice owns the VFS file-permission inheritance matrix
  from `journal3-1.2.*`.

Dependency closure:

- No new support component is needed. The patch reuses the lane-local VFS I/O
  dynamic corpus model and adds a bounded native PHP permission-inheritance
  profile for rollback-journal creation.
