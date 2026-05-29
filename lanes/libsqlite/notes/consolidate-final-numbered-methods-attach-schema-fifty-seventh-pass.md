# Consolidate Attach Schema Numbered Method References Fifty-Seventh Pass

Renamed the direct attach/TEMP/WAL schema-cache publication-window test and WordPress smoke away from their generated numbered suffix. The covered behavior still calls the canonical `SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan()` production entry point; no numbered production compatibility shim was added.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheConsolidatedPublicationWindowTest.php`
- `php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-consolidated-publication-window.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheConsolidatedPublicationWindowTest.php`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-consolidated-publication-window.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a direct consolidation of attach/schema test and example references onto the existing canonical planner.
