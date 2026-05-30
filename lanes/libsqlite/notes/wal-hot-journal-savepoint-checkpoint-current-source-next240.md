# WAL Hot-Journal Savepoint Checkpoint Current Source Next240

Status: focused PHP behavior growth for `wal-hot-journal-savepoint-checkpoint-current-source-next240`.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`. It starts after the accepted next236 statement-finalizer handoff and admits the next writer commit/autocheckpoint baseline only when commit receipts still match the checkpoint current source: source token, released writer generation, commit generation, schema cookie, checkpoint database digest, clean page-cache digest, WAL-index salt, `mxFrame`, checkpoint frame, commit mark, writer-lock release, WAL-hook receipt, and autocheckpoint receipt.

Blocked receipts retain the checkpoint baseline when they are stale, miss a finalized statement, see the hot journal or an open savepoint, keep dirty checkpoint cache state, lack the commit mark, fail to release the writer lock, or miss WAL-hook/autocheckpoint receipts.

Application smoke: `application-wal-hot-journal-savepoint-checkpoint-current-source-next240.php` models a copied `wp_options` import where active plugin, autoload, and option-name index readers finalized after hot-journal recovery and WAL savepoint checkpointing. The next writer commit may publish a WAL-index/autocheckpoint baseline only after all receipts match the checkpointed database and clean page cache.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext240Test.php`
  - `1 test files, 94 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next240.php --self-test`
  - `application-wal-hot-journal-savepoint-checkpoint-current-source-next240 self-test passed`
- PHP lint and `git diff --check -- lanes/libsqlite` are part of the handoff verification.

Expected dashboard delta: `phpPass` +94 from the focused PASS lines in `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext240Test.php`. Mapped upstream coverage is unchanged; this is additional focused WAL/pager current-source behavior over existing hot-journal/savepoint/checkpoint inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next236 finalizer release, next233 prepared-statement admission, checkpoint publication/reopened-handle receipts, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, checkpoint transaction planning, and batch207 next236 WAL hot-journal savepoint checkpoint coverage. The new surface is the next-writer commit/autocheckpoint baseline admission after finalizers have already released checkpoint readers.

Dependency closure: no new support component is needed. The slice reuses lane-local finalizer release metadata, WAL-index salt/mxFrame receipts, checkpoint-frame receipts, page-cache digests, WAL-hook receipts, and autocheckpoint fences.

Next task: continue with broader pager/VFS transaction application or a distinct WAL durability edge; avoid another finalizer or prepared-statement admission wrapper unless it applies a new pager state transition.
