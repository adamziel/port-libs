# ATTACH Temp WAL Schema Trigger Source Reprepare

- Behavior: adds `SQLiteAttachTempWalSchemaTriggerPlan::triggerSourceRepreparePlan()`, a bounded native PHP planner for prepared trigger current/next source invalidation across TEMP, main, and attached schemas.
- Upstream SQLite edge: active prepared triggers keep running against their current source until reset, while inactive triggers whose source, target, target columns, or body dependency resolution changes report `SQLITE_SCHEMA` on the next step. Unqualified trigger names can be shadowed by TEMP DDL; schema-qualified names stay pinned.
- WordPress path: copied `wp_options` import triggers are checked across main, temp staging, and attached multisite catalogs, including WAL page-one schema-cookie sources and active trigger reset routing.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaTriggerSourceReprepareTest.php` passed with `1 test files, 68 assertions, 0 failures`.
- Example smoke: `php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-trigger-source-reprepare.php` emits the current/next reprepare plan for temp-shadowed and schema-qualified WordPress option triggers.
- Non-overlap: avoids accepted batch88 ATTACH temp/WAL schema-cache routing and schema-write cookie planning; this slice only covers prepared trigger current-source invalidation between already-built current and next catalogs.
- Dependency closure: no new support component is needed; this reuses `SQLiteAttachedSchemaCatalog`, `SQLiteAttachTempViewTriggerResolution`, and existing WAL schema-cookie helpers.
