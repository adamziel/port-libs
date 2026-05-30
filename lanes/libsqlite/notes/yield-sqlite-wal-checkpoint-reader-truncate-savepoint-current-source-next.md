# WAL checkpoint reader truncate savepoint current-source next128

Status: focused PHP behavior growth for
`wal-checkpoint-reader-truncate-savepoint-current-source-next128`.

This slice adds `SQLiteWalCheckpointReaderTruncateSavepointCurrentSourceNextPlan`.
It models the SQLite WAL path where `ROLLBACK TO` a savepoint truncates the
current WAL to a retained prefix, a live reader pins that retained prefix, and
`PRAGMA wal_checkpoint(TRUNCATE)` must preserve the WAL until the reader
releases. After release, the same current images are checkpointed into the
database and the WAL sidecar truncates to zero bytes.

Application smoke:
`application-wal-checkpoint-reader-truncate-savepoint-current-source-next.php`
models a copied `wp_options` import where a plugin settings savepoint discards
tail frames for autoload/transient/active_plugins updates while the current
reader keeps the retained schema/siteurl prefix visible.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointReaderTruncateSavepointCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 72 assertions, 0 failures
```

Dashboard delta: `phpPass` should move from `52453` to `52525` from 72 newly
passing focused PASS lines. Mapped upstream coverage remains `606 / 1589`
because this is focused current-source WAL behavior over already mapped
checkpoint/savepoint primitives, not a fresh manifest-backed inventory row.

Non-overlap: this avoids accepted WAL reader checkpoint savepoint truncate
next123 stale-reader source evidence, WAL checkpoint savepoint hot-journal
next126, WAL restart/truncate savepoint reader next105, WAL byte truncation,
WAL checkpoint transaction, VFS savepoint rollback, rollback-journal apply /
commit, super-journal commit, VFS sync/write/lock clusters, and the accepted
B-tree, JSON table, SELECT SQL, and encoding clusters. The new surface is the
retained current WAL prefix after savepoint rollback blocking truncate until
the current reader is released.

Dependency closure: no new support component is needed. The slice reuses
lane-local WAL parsing, savepoint WAL prefix truncation, durable checkpoint,
and database-page visibility helpers.

Next task: continue with broader pager/VFS transaction application or another
WAL durability edge that applies state beyond retained-prefix reader
visibility.
