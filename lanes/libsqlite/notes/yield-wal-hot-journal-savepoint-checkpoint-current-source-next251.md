# WAL hot-journal savepoint checkpoint current-source next251

- Slice: WAL sidecar reset/truncate admission after an admitted next246 durable checkpoint handoff.
- Behavior: `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next251AdmitWalSidecarReset()` requires every checkpoint reader read-mark to release before the WAL header is rewritten with a fresh salt, stale committed frames are retired by truncating the sidecar to zero bytes, and the empty restarted WAL is synced under the same source token, generation, checkpoint frame, and exclusive lock.
- WordPress path: `examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next251.php` models a copied WordPress database import that can retire a hot-journal checkpoint WAL sidecar only after schema/options/terms readers release.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext251Test.php` passed with `1 test files, 89 assertions, 0 failures`.
- Expected dashboard movement: `phpPass +89` from `129612` to `129701`; mapped upstream coverage unchanged at `659 / 1589`.
- Dependency closure: no new support component needed; this reuses next246 durable handoff metadata with native PHP reader-release, WAL salt/header, truncate, sync, and exclusive-lock receipts.
- Non-overlap: does not repeat durable page writes, reader snapshot matching, checkpoint transaction planning, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, VFS sync planning/apply, file locking, SELECT, JSON, or B-tree surfaces.
