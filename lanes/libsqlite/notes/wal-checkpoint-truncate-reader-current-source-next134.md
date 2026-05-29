# WAL checkpoint truncate reader current-source next134

- Slice: `wal-checkpoint-truncate-reader-current-source-next134`.
- Behavior: adds `SQLiteWalCheckpointTruncateReaderCurrentSourceNextPlan`, which validates the current reader WAL source before a `TRUNCATE` checkpoint, preserves that reader on the old WAL while reset is blocked, then proves the released checkpoint removes the old sidecar and the next writer/reader starts from a fresh WAL generation.
- WordPress path: copied `wp_options` imports can diagnose the boundary where a long-lived current reader still sees the old option pages while the next import transaction writes a fresh WAL generation after checkpoint truncation.
- Focused evidence:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointTruncateReaderCurrentSourceNext134Test.php` -> `1 test files, 69 assertions, 0 failures`.
  - `php -l lanes/libsqlite/src/SQLiteWalCheckpointTruncateReaderCurrentSourceNextPlan.php`.
  - `php -l lanes/libsqlite/tests/SQLiteWalCheckpointTruncateReaderCurrentSourceNext134Test.php`.
  - `php -l lanes/libsqlite/examples/wordpress-wal-checkpoint-truncate-reader-current-source-next134.php`.
  - `php lanes/libsqlite/examples/wordpress-wal-checkpoint-truncate-reader-current-source-next134.php --self-test`.
- Dashboard delta: `phpPass` moves from `56681` to `56750` for the 69 verified focused assertions. Mapped upstream coverage remains conservative because this composes already mapped WAL checkpoint, current-reader, truncate, and append-generation primitives.
- Non-overlap: avoids accepted WAL checkpoint reader hot-journal next132, hot-journal restart next129/next131, savepoint truncate next130, reader checkpoint restart/savepoint next127, WAL byte truncation, rollback-journal apply/commit, super-journal commit, VFS writer/sync/lock clusters, JSON table source/cursor/constraints, SQL SELECT text/subquery/group/order, B-tree page/freelist/overflow clusters, and Unicode GLOB. The new surface is the old-current-reader source pin versus fresh post-TRUNCATE WAL generation boundary.
- Dependency closure: no new support component is needed; this reuses native PHP WAL checkpoint, reader snapshot, and append transaction primitives.
- Next task: continue with broader pager/VFS transaction application or another distinct WAL durability edge; avoid another reader/checkpoint wrapper unless it applies a new byte or lock transition.
