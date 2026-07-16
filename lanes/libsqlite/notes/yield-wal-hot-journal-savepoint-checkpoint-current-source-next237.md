# WAL Hot-Journal Savepoint Checkpoint Current Source Next237

- Slice: `wal-hot-journal-savepoint-checkpoint-current-source-next237`.
- Behavior: after a next234 durable hot-journal/savepoint checkpoint handoff, admit reuse of the WAL sidecar only when the byte length exactly matches `32 + checkpoint_frame * (24 + page_size)`, frame count and last commit frame match the checkpoint, salts are valid, the WAL digest still matches, hot journal/savepoint state is closed, the directory is synced, and reader pins do not cross the checkpoint frame.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext237Test.php` passed with `1 test files, 84 assertions, 0 failures`.
- Application smoke: `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next237.php --self-test` passes and models a copied Application plugin import reusing a durable WAL sidecar after hot-journal/savepoint checkpoint recovery.
- Non-overlap: this extends next234 durable handoff into WAL sidecar-boundary admission. It does not repeat durable sync receipt admission, WAL-index reopen readmarks, savepoint byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, checkpoint transaction planning, or the accepted next234 WAL hot-journal savepoint checkpoint behavior.
- Dependency closure: no new support component needed; the slice reuses existing native PHP WAL/current-source metadata and lane-local reader-pin/sidecar receipts.
- Expected dashboard movement: `phpPass +84`; mapped upstream coverage unchanged because this is behavior-backed PHP coverage, not a newly mapped upstream manifest unit.
