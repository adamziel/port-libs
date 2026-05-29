# WAL hot-journal savepoint checkpoint current-source next244

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a durability seal after accepted next240 autocheckpoint admission.

Behavior covered:

- Requires an admitted next240 WAL hot-journal/savepoint/checkpoint baseline.
- Admits durable publication only when every dirty checkpoint page is written, every committed WAL frame is synced, exclusive lock and database/WAL/directory fsync receipts are present, the hot journal is deleted, and stale WAL preservation is not observed.
- Requires reopened reader acknowledgements to match the same source token, schema cookie, database digest, WAL-index salt, mxFrame, checkpoint frame, and commit generation before the sidecar can be reset/truncated.
- Blocks stale source, stale digest, stale salt, missing sync, unexpected page/frame, missing reader reopen/readmark, visible hot journal, and visible stale WAL cases.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext244Test.php`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next244.php --self-test`
- PHP lint on changed PHP files.
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed. This reuses accepted next240 WAL-index baseline metadata, VFS durable receipt evidence, reader acknowledgements, and page-cache digests.

Non-overlap: this does not repeat next240 commit/autocheckpoint baseline admission, next236 finalizer release, checkpoint transaction planning, VFS savepoint rollback, rollback-journal commit/apply, WAL byte truncation, or WAL-index reopen verification.
