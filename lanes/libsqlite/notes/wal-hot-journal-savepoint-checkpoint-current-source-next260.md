# WAL hot-journal savepoint checkpoint current-source next260

Status: focused PHP behavior growth for `wal-hot-journal-savepoint-checkpoint-current-source-next260`.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`. It layers on the accepted next246 durable handoff and admits a checkpoint as the current source only when rollback-journal delete evidence, savepoint retained-WAL prefix evidence, checkpoint page/frame sync evidence, and reopened reader tokens all match the same current-source generation.

Application smoke: `application-wal-hot-journal-savepoint-checkpoint-current-source-next260.php` models a copied `wp_options` plugin import that restarts after hot-journal recovery, closes the plugin savepoint prefix, checkpoints dirty pages, and advances reopened readers only after the evidence matches.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Test.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next260.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Test.php`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next260.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `137964` to `138068` from 104 newly passing focused PASS lines. Mapped upstream coverage remains `683 / 1589`; this is a focused WAL/pager source-ordering behavior over existing hot-journal/savepoint/checkpoint inventory rather than a fresh upstream denominator row.

Non-overlap: this avoids accepted next246 durable VFS handoff, next244/next240 durable-source publication, next223/next183 hot-journal reader-token admission, checkpoint transaction planning, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, super-journal commit, B-tree, JSON table, SELECT, and encoding clusters. The new surface is the post-handoff source-order fence spanning rollback-journal, savepoint WAL prefix, checkpoint sync, and reader token evidence.

Dependency closure: no new support component is needed. The slice reuses lane-local rollback-journal receipt, savepoint WAL prefix, checkpoint durability, and reader-token evidence.

Next task: continue with broader pager/VFS transaction application or another distinct WAL durability edge; avoid another checkpoint wrapper unless it applies a new source-ordering or durable-write rule.
