# WAL savepoint reader checkpoint current source next117

This slice adds `SQLiteWalSavepointReaderCheckpointCurrentSourceNextPlan`.
It models the retry boundary where a reader still advertises the pre-rollback
WAL source after `ROLLBACK TO`, but checkpoint planning must use the retained
current WAL prefix rather than stale reader tail frames.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalSavepointReaderCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalSavepointReaderCheckpointCurrentSourceNext117Test.php
php -l lanes/libsqlite/examples/wordpress-wal-savepoint-reader-checkpoint-current-source-next117.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalSavepointReaderCheckpointCurrentSourceNext117Test.php
php lanes/libsqlite/examples/wordpress-wal-savepoint-reader-checkpoint-current-source-next117.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard delta: `phpPass` +71, from `44622` to `44693`, from the
new focused PASS lines in `SQLiteWalSavepointReaderCheckpointCurrentSourceNext117Test.php`.
Mapped coverage is unchanged because this is PHP behavior coverage without a
new manifest-backed upstream row.

Non-overlap: avoids accepted WAL savepoint/checkpoint reader recovery next104
and next111, savepoint byte truncation, VFS savepoint rollback apply, WAL
checksum/salt recovery, WAL MVCC hot-journal checkpoint snapshots,
rollback-journal/super-journal paths, and accepted JSON/B-tree/SQL/VFS
clusters. The new surface is explicit stale reader-source rejection for
checkpoint source selection after savepoint rollback.

Dependency closure: no new support component is needed. The slice reuses
existing native PHP WAL parsing, savepoint WAL truncation, reader visibility,
and durable checkpoint result primitives.

Next task: continue with broader pager/VFS transaction application or another
non-overlapping WAL durability edge; do not add another reader-source wrapper
unless it applies a distinct current-source transition.
