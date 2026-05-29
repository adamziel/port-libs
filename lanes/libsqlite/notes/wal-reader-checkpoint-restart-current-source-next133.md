# WAL reader checkpoint restart current-source next133

Status: focused PHP behavior growth for WAL reader source-handle identity after a RESTART checkpoint.

This slice adds `SQLiteWalReaderCheckpointRestartCurrentSourceNextPlan`, a bounded native PHP planner for the SQLite edge where a current reader has an open WAL source, all readers then release, a RESTART checkpoint replaces the `-wal` path with a new generation, and the next writer appends frames to that restarted generation. The plan proves that the current reader must keep resolving pages from its original WAL bytes instead of reopening the replaced path, while the next reader sees the restarted generation.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderCheckpointRestartCurrentSourceNext133Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 76 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-wal-reader-checkpoint-restart-current-source-next133.php --self-test
wordpress-wal-reader-checkpoint-restart-current-source-next133 self-test passed
```

Additional verification:

```text
php -l lanes/libsqlite/src/SQLiteWalReaderCheckpointRestartCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalReaderCheckpointRestartCurrentSourceNext133Test.php
php -l lanes/libsqlite/examples/wordpress-wal-reader-checkpoint-restart-current-source-next133.php
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass` +76, from 55029 to 55105, from the 76 independent PASS lines in `SQLiteWalReaderCheckpointRestartCurrentSourceNext133Test.php`. Mapped upstream coverage remains `606 / 1589`; this is focused PHP behavior coverage over existing WAL/checkpoint inventory rather than a newly mapped upstream manifest row.

Non-overlap: avoids accepted WAL reader checkpoint savepoint truncate next130, hot-journal checkpoint restart next129, checkpoint reader restart snapshot next124, reader checkpoint restart validation next89, WAL byte truncation, WAL checkpoint transaction planning, VFS savepoint rollback, rollback-journal commit/apply, VFS writer/sync/lock clusters, B-tree/JSON/SQL/encoding clusters. The new surface is source-handle identity when the filesystem `-wal` path has already been replaced by a restarted generation.

Dependency closure: no new support component is needed. The slice reuses native PHP WAL checksum parsing, durable checkpoint result planning, and reader snapshot page-image helpers.

Next task: continue with broader pager/VFS transaction application only if it applies bytes through real file-handle state without repeating restart/checkpoint source wrappers.
