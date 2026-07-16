# full-run-parity-application-wal-rollback-json-dynamic-20260531T065831Z-0

Micro-slice: `full-run-parity-application-wal-rollback-json-dynamic-20260531T065831Z-0`

Behavior added:
- `SQLiteJsonImportRollbackWalPlan::dynamicMalformedInsertedInitialValueScenarios()` now generates source-neutral application JSON WAL rollback fixtures where an import mutates one existing tenant setting, then attempts to insert a missing setting whose initial JSON image is malformed.
- The focused parity test proves the failed insert statement is unwound from `final_rows`, restores the staged inserted page image, discards only that statement's WAL frame, then the outer batch rollback restores the pre-batch database image and truncates the WAL back to the header.
- The application example summary now reports malformed inserted-initial-value scenario counts, statuses, inserted IDs, statement-level restored pages, and failed WAL frame indexes.

Focused evidence:
- Before change: `php -d memory_limit=1024M` with the accepted `HEAD` version of `SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - Result: `1 test files, 4552 assertions, 0 failures`
- After change: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - Result: `1 test files, 5015 assertions, 0 failures`

Expected selected movement:
- Adds 463 focused assertions to the existing app-WAL rollback JSON dynamic parity file.
- Mapped coverage remains `1589 / 1589`; this is behavior-backed coverage over an existing mapped application WAL/JSON parity surface.

Non-overlap:
- This is a malformed inserted-initial-value rollback slice. It does not repeat plain app-WAL rollback, preexisting WAL prefixes, deferred failure, retry, same-key tenant collision, successful inserted-setting rollback, duplicate inserted setting IDs, missing/partial WAL tails, WAL header/frame corruption admission, rollback-journal apply/commit, WAL checkpoint transactions, VFS writer/sync/lock/savepoint rollback, JSON table source/cursor/constraint work, B-tree page relocation/freeblock materialization, SELECT SQL text/group/order/subquery clusters, row-value dynamic parity, or source-neutral cleanup.

Dependency closure:
- No new support component is needed. The slice reuses the existing native PHP JSON mutation, JSON5 diagnostics, savepoint statement rollback, WAL rollback, WAL byte parsing, and source-neutral tenant key handling.
