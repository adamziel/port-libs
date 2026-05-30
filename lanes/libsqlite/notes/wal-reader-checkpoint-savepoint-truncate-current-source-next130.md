# WAL reader checkpoint savepoint truncate current-source next130

Status: focused PHP behavior growth for the next-open reader boundary after a
savepoint rollback and drained-reader TRUNCATE checkpoint.

This slice adds `SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan`.
It composes WAL savepoint prefix truncation with a reader-pinned TRUNCATE
checkpoint, then verifies the follow-up state where the reader has drained, the
WAL sidecar is removed, and the next opened reader resolves retained
`wp_options` pages from the checkpointed database image rather than a WAL
snapshot.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNext130Test.php
php -l lanes/libsqlite/examples/application-wal-reader-checkpoint-savepoint-truncate-current-source-next130.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNext130Test.php
php lanes/libsqlite/examples/application-wal-reader-checkpoint-savepoint-truncate-current-source-next130.php --self-test
```

Focused result: `1 test files, 63 assertions, 0 failures`.

Application smoke: copied `wp_options` import pages keep the retained
`siteurl` page visible to the current reader while the next reader opens after
TRUNCATE with no WAL sidecar and reads all pages from the checkpointed database
image.

Expected dashboard movement: `phpPass` +63, from 54071 to 54134. Mapped
coverage remains `606 / 1589`; this is focused PHP behavior coverage over the
existing WAL/checkpoint/savepoint inventory rather than a new manifest row.

Non-overlap: avoids accepted WAL checkpoint reader truncate savepoint next128,
WAL reader checkpoint savepoint truncate next123, WAL byte truncation,
checkpoint transaction planning, VFS savepoint rollback/write/sync/lock
application, rollback-journal commit/apply, hot-journal recovery, B-tree,
JSON, SQL executor, and encoding clusters. The new surface is the no-WAL
next-open reader state after the current reader releases a savepoint-truncated
TRUNCATE checkpoint.

Dependency closure: no new support component is needed. The patch reuses
native PHP WAL parsing/checksum validation, savepoint WAL prefix truncation,
durable checkpoint result materialization, and reader snapshot primitives.

Next task: continue with broader pager/VFS transaction application or another
distinct WAL durability edge; avoid another checkpoint wrapper unless it
applies a new persistence or source-selection rule.
