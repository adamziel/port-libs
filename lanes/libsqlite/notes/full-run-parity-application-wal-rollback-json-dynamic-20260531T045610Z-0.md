# full-run-parity-application-wal-rollback-json-dynamic-20260531T045610Z-0

Micro-slice: `full-run-parity-application-wal-rollback-json-dynamic-20260531T045610Z-0`

Behavior added:
- `SQLiteJsonImportRollbackWalPlan::dynamicPartialWalTailScenarios()` now generates deterministic application JSON WAL rollback fixtures where a preexisting WAL prefix plus one current-batch frame is followed by an incomplete WAL frame tail.
- The focused parity test now proves those unaligned WAL byte streams reject with the native partial-frame-tail diagnostic before rollback planning can silently treat the truncated current batch as durable.
- The existing application WAL rollback JSON dynamic parity smoke summary now reports the partial-tail scenario count, diagnostics, complete frame counts, and partial byte counts.

Focused evidence:
- Before change: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - Result: `1 test files, 2361 assertions, 0 failures`
- After change: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - Result: `1 test files, 2458 assertions, 0 failures`

Expected selected movement:
- Adds 97 focused PASS/assertion lines to the existing app-WAL rollback JSON dynamic parity file.
- Mapped coverage remains `1589 / 1589`; this is additional corruption-prevention coverage over the existing application WAL rollback JSON parity surface.

Non-overlap:
- This is a partial WAL frame-tail guard for application JSON rollback. It does not repeat aligned missing-WAL-frame coverage, rollback-journal apply/commit, WAL checkpoint transactions, VFS writer/sync/lock/savepoint rollback, WAL byte truncation, JSON table source/cursor/constraint work, B-tree page relocation/freeblock materialization, SELECT SQL text/group/order/subquery clusters, row-value dynamic parity, or source-neutral cleanup.

Dependency closure:
- No new support component is needed. The slice reuses the existing native PHP JSON import savepoint plan, savepoint/WAL rollback metadata, WAL header/frame parsing, and dynamic app-WAL parity fixture generation.
