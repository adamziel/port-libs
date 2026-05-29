# SQLite Attach Temp WAL Schema-Cache Locale Publish Window

Behavior: uses canonical `SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan()` for the locale-publish schema-cache window. The slice starts from the prior handoff state, carries dependency receipts, and verifies main WAL cookie advance, temp schema cookie advance, queue index rename expiry, audit table drop expiry, handoff table rename expiry, publish WAL visibility, attached review schema visibility, archive detach removal, and stable report metadata lookup preservation.

Consolidation:

- Renamed the direct test/example/note away from the generated current-source suffix.
- Removed stale references to the old numbered production class and method. Production already uses the canonical attach schema-cache class.

Validation:

- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheLocalePublishWindowTest.php`
- `php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-locale-publish-window.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheLocalePublishWindowTest.php`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-locale-publish-window.php --self-test`
- `git diff --check -- lanes/libsqlite`
