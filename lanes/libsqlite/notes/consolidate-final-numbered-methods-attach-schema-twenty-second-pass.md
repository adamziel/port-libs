## Attach/Schema Numbered Method Consolidation Twenty-Second Pass

Consolidated one remaining direct attach/schema caller surface onto the stable
`SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan()` production
entrypoint by renaming the publish-window focused test and WordPress example
away from generated worker-numbered names.

No production compatibility shim was added. The production attach schema-cache
behavior remains in the existing consolidated helper.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCachePublishWindowTest.php`
- `php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-publish-window.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCachePublishWindowTest.php`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-publish-window.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a caller/file
name consolidation over existing attach/schema-cache PHP behavior.
