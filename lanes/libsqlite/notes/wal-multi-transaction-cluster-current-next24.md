# WAL Multi Transaction Cluster Current Next24

Status: focused PHP behavior growth for WAL multi-transaction current/next visibility.

What changed:
- Added `SQLiteWalMultiTransactionClusterPlan`, a bounded native PHP planner that groups committed WAL frames into transaction clusters, materializes per-cluster before/after page visibility, reports superseded frames inside a transaction, and compares current reader visibility with the next checkpointed database image.
- Added `SQLiteWalMultiTransactionClusterCurrentNext24Test.php` with 60 independent PASS cases over three committed WAL transactions, a valid uncommitted tail, database growth, future-page handling inside earlier clusters, pinned readers, empty WAL behavior, and validation errors.
- Added `application-wal-multi-transaction-cluster.php` to smoke copied `wp_options` WAL repair diagnostics without requiring ext/sqlite.

Verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalMultiTransactionClusterCurrentNext24Test.php`
- Result: `1 test files, 60 assertions, 0 failures` with 60 PASS lines.

Status delta:
- `phpPass`: `8166 -> 8226` (+60 verified focused PASS lines).
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior growth over already inventoried WAL transaction behavior, not a fresh upstream inventory unit.

Non-overlap:
- Avoids accepted WAL corrupt recovery boundary, WAL savepoint byte truncation, savepoint page-image rollback, VFS savepoint rollback apply, WAL checkpoint transactions, VFS rollback-journal apply/commit, super-journal commits, VFS sync/apply, VFS file writer/locked writer, B-tree overflow/page-move/root-collapse clusters, JSON table cursor/source/constraint clusters, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB.
- This slice is specifically the current/next visibility of multiple committed WAL transaction clusters with an uncommitted tail.

Dependency closure:
- No new shared support component is needed. The slice reuses lane-local WAL parsing/checksum/frame/reader visibility primitives and adds a bounded planner under `lanes/libsqlite/src`.

Next:
- Continue with non-overlapping WAL/pager VFS transaction application, durable fsync/file-handle behavior, SQL executor/planner correctness, JSON dynamic planner work, or a distinct release/all-suite blocker.
