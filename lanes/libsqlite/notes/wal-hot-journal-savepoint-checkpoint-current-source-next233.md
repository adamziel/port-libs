# WAL Hot-Journal Savepoint Checkpoint Current Source Next233

Status: focused PHP behavior growth for `wal-hot-journal-savepoint-checkpoint-current-source-next233`.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`. It admits prepared statements after an accepted next229 checkpoint current-source handle publication only when each statement is bound to the new source token, writer generation, schema cookie, checkpoint database digest, admitted reopened handle, root-page digests, schema reparse receipt, and read-lock receipt.

Blocked statements expire before the next step when they use a stale source token/generation/schema cookie/database digest, refer to an unadmitted handle, miss root-page coverage, retain a hot journal, keep a savepoint open, hold a dirty cache image, or lack schema/read-lock receipts.

Application smoke: `application-wal-hot-journal-savepoint-checkpoint-current-source-next233.php` covers copied `wp_options` SELECT reuse after a hot-journal savepoint checkpoint publication. It proves schema, table, and option-name index statements can reuse the current source only after their root-page digests and receipts match the checkpointed source.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext233Test.php`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next233.php --self-test`
- PHP lint and `git diff --check -- lanes/libsqlite` are part of the handoff verification.

Expected dashboard delta: `phpPass` +67 from the focused PASS lines in `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext233Test.php`. Mapped upstream coverage is unchanged; this is additional current-source PHP behavior over existing WAL/hot-journal/checkpoint inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next223 publication receipts, next224/next229 reset and reopened-handle coverage, next218 restart/truncate admission, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, checkpoint transaction planning, and batch201 next228-next230 WAL hot-journal savepoint checkpoint coverage. The new behavior is specifically prepared-statement admission after the published checkpoint current source is already visible through reopened handles.

Dependency closure: no new support component is needed. The slice reuses lane-local current-source token, schema-cookie, root-page digest, and read-lock receipt metadata.

Next task: continue with broader pager/VFS transaction application or another distinct WAL durability edge; avoid another checkpoint publication wrapper unless it applies a new admission layer.
