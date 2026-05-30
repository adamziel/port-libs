# consolidate-final-numbered-methods-attach-schema-twelfth-pass

Consolidated the remaining direct attach/schema numbered production class names in this slice:

- `SQLiteAttachWalTempTransactionCurrentNextPlan` -> `SQLiteAttachWalTempTransactionPlan`
- `SQLiteAttachWalTempSchemaCacheCurrentNextPlan` -> `SQLiteAttachWalTempSchemaCacheTransactionPlan`
- `SQLiteImportJsonSchemaSavepointCurrentNextPlan` -> `SQLiteImportJsonSchemaSavepointPlan`

Direct tests and Application examples were renamed to stable unsuffixed names and migrated to the canonical classes. No numbered production compatibility shims were left behind.

Focused verification:

- `php -l` on all changed PHP files: pass
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachWalTempTransactionTest.php lanes/libsqlite/tests/SQLiteAttachWalTempSchemaCacheTransactionTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheTransactionSourceTest.php lanes/libsqlite/tests/SQLiteImportJsonSchemaSavepointTest.php`: `4 test files, 337 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-attach-wal-temp-transaction.php --self-test && php lanes/libsqlite/examples/application-attach-wal-temp-schema-cache-transaction.php --self-test && php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-transaction-source.php --self-test && php lanes/libsqlite/examples/application-import-json-schema-savepoint.php --self-test`: pass
- `git diff --check -- lanes/libsqlite`: pass

Dependency closure: no new support component is needed; this is a naming consolidation over existing native attach/schema, WAL/temp transaction, statement lifecycle, JSON import, and savepoint behavior.

Lane status: no `phpPass` or mapped coverage movement claimed because this patch consolidates production naming without adding behavior coverage.
