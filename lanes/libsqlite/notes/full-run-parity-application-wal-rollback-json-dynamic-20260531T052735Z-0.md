# full-run-parity-application-wal-rollback-json-dynamic-20260531T052735Z-0

Micro-slice: `full-run-parity-application-wal-rollback-json-dynamic-20260531T052735Z-0`

Behavior added:
- `SQLiteJsonImportRollbackWalPlan::dynamicInsertedSettingRollbackScenarios()` now generates deterministic generic application JSON WAL rollback fixtures where a failing import batch inserts two new tenant-scoped settings after mutating an existing row.
- The focused parity test proves rollback restores the original database bytes, truncates the WAL back to the header, discards the inserted pages and applied frames, preserves source-neutral tenant IDs and inserted setting IDs, and keeps the malformed statement rollback isolated to its own frame.
- The existing application WAL rollback JSON dynamic parity smoke summary now reports inserted-setting scenario counts, statuses, inserted IDs, restored pages, and post-rollback WAL frame counts.

Focused evidence:
- Before change, from the previous accepted note for this test: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - Result: `1 test files, 2825 assertions, 0 failures`
- After change: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - Result: `1 test files, 3312 assertions, 0 failures`

Expected selected movement:
- Adds 487 focused PASS/assertion lines to the existing app-WAL rollback JSON dynamic parity file.
- Mapped coverage remains `1589 / 1589`; this is additional behavior-backed coverage over the existing application WAL rollback JSON parity surface.

Non-overlap:
- This is an inserted-setting rollback parity slice. It does not repeat previous app-WAL dynamic parity cases for plain rollback, preexisting WAL prefixes, deferred failure, retry, same-key tenant collision, missing WAL tail, partial WAL tail, WAL header admission, rollback-journal apply/commit, WAL checkpoint transactions, VFS writer/sync/lock/savepoint rollback, JSON table source/cursor/constraint work, B-tree page relocation/freeblock materialization, SELECT SQL text/group/order/subquery clusters, row-value dynamic parity, or source-neutral cleanup.

Dependency closure:
- No new support component is needed. The slice reuses the existing native PHP JSON mutation, savepoint, WAL rollback, WAL byte parsing, and source-neutral tenant key handling.
