# WAL checkpoint hot-journal truncate current-source next138

Status: focused PHP behavior growth for `wal-checkpoint-hot-journal-truncate-current-source-next138`.

This slice adds `SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan`. It models the current-source boundary where hot rollback-journal recovery must restore the database image before a savepoint rollback truncates the WAL prefix and a follow-up TRUNCATE checkpoint is allowed to remove the WAL sidecar after the pinned reader drains.

Application smoke: `application-wal-checkpoint-hot-journal-truncate-current-source-next.php` covers copied `wp_options` import repair where a failed import leaves dirty database pages, the hot rollback journal restores clean pages, a plugin-batch savepoint discards later WAL frames, and the final TRUNCATE checkpoint exposes database-only pages to the next reader.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/src/SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-wal-checkpoint-hot-journal-truncate-current-source-next.php`
- `php lanes/libsqlite/examples/application-wal-checkpoint-hot-journal-truncate-current-source-next.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `59517` to `59598` from 81 newly passing focused PASS lines. Mapped upstream coverage remains `606 / 1589`; this is focused WAL/pager behavior over existing hot-journal/checkpoint/savepoint inventory rather than a fresh upstream denominator row.

Non-overlap: this avoids accepted WAL reader checkpoint savepoint truncate next130, checkpoint hot-journal reader next122, hot-journal savepoint next114, checkpoint transaction planning, VFS savepoint rollback/write/sync/lock application, rollback-journal commit/apply, master-journal and hot-cache pager clusters, B-tree, JSON, SQL executor, and encoding clusters. The new surface is specifically the combined hot-journal current-source recovery plus savepoint-truncated WAL TRUNCATE checkpoint admission.

Dependency closure: no new support component is needed. The slice reuses native PHP rollback-journal parsing/recovery, WAL parsing/checkpoint materialization, and savepoint WAL prefix truncation primitives.

Next task: continue with broader pager/VFS transaction application or another distinct WAL durability edge; avoid another checkpoint wrapper unless it applies a new source-ordering or durable-write rule.
