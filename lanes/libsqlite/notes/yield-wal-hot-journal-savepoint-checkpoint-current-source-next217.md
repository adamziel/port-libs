# WAL Hot-journal Savepoint Checkpoint Current-source Next217

Status: focused PHP behavior growth for `wal-hot-journal-savepoint-checkpoint-current-source-next217`.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`. It sits after the accepted next211 checkpoint-reader acknowledgement admission and requires durable receipt fencing before the next current source can be published: retained readers must acknowledge the exact current-source token/frame/cookie/schema/image digest, reopened readers must carry a deterministic reopen fence token, and every row must prove hot-journal deletion, WAL sync, and directory sync durability. Stale, missing, or orphan receipts block publication.

Application smoke: `application-wal-hot-journal-savepoint-checkpoint-current-source-next217.php` models a copied `wp_options` import that only advances after current import readers acknowledge page images and an old plugin-settings reader is fenced for reopen.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext217Test.php`
- Result: `1 test files, 68 assertions, 0 failures`, with `68` PASS lines.

Application smoke:

- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next217.php --self-test`
- Result: `application-wal-hot-journal-savepoint-checkpoint-current-source-next217 self-test passed`

Expected dashboard movement:

- `phpPass`: `+68` focused PASS lines.
- `benchmarkDenominator.mapped`: unchanged; this is current-source PHP behavior over existing WAL/hot-journal/savepoint checkpoint inventory.

Non-overlap:

Avoids next211 page digest admission, next208 reader slot validation, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, checkpoint transaction planning, WAL restart/truncate reader snapshots, and hot rollback-journal recovery. The new behavior is specifically durable receipt admission/fencing after checkpoint reader acknowledgements already exist.

Dependency closure: no new support component is needed. The slice reuses lane-local reader acknowledgement rows, hot-journal delete receipts, WAL sync receipts, and directory sync receipts.

Next task: wire these durable receipt rows into the native pager/VFS checkpoint executor so source publication is owned by applied transaction state rather than bounded admission fixtures.
