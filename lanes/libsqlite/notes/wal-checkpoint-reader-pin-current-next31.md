# WAL checkpoint reader pin current next31

Status: focused PHP corpus growth for WAL checkpoint reader-pin current/next visibility.

## Behavior

- Added `SQLiteWal::checkpointReaderPinCurrentNext()` to compose existing WAL read-mark planning, durable checkpoint results, and reader snapshots into a bounded current-reader/next-reader handoff.
- Covers pinned SHM-style read marks where the current reader stays on an older commit frame while a next reader sees the latest preserved WAL snapshot.
- Covers unpinned restart/truncate checkpoints where the WAL can reset or truncate and both current/latest readers are served from the checkpointed database image.
- Adds `application-wal-checkpoint-reader-pin-current-next31.php` for copied `wp_options` WAL diagnostics without requiring ext/sqlite.

## Verification

- `php -l lanes/libsqlite/src/SQLiteWal.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteWalCheckpointReaderPinCurrentNext31Test.php` -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-checkpoint-reader-pin-current-next31.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointReaderPinCurrentNext31Test.php` -> `1 test files, 49 assertions, 0 failures` with 49 PASS lines.
- `php lanes/libsqlite/examples/application-wal-checkpoint-reader-pin-current-next31.php` -> prints restart checkpoint reason, preserved WAL action, pinned current frame `2`, next reader frame `4`, stable current reader, and latest next-reader visibility.

## Non-overlap

This avoids accepted WAL checkpoint transactions, WAL byte truncation, WAL append transactions, WAL SHM restart/read-mark diagnostics, WAL reader/writer snapshot visibility, VFS file writer/sync/rollback/savepoint application, JSON table source/cursor/constraint work, B-tree page move/root-collapse/overflow release, SELECT SQL text/subquery/grouping/expression-order clusters, and Unicode GLOB work. The new surface is the composed current-reader versus next-reader visibility boundary for reader-pinned checkpoint attempts.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP WAL parsing/checksum, read-mark planning, checkpoint result, and reader snapshot primitives.

## Next

Continue WAL work toward pager/VFS transaction application and durability edges beyond this read-only current/next reader-pin composition.
