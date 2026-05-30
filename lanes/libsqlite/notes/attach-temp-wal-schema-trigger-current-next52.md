# Attach temp WAL schema trigger current-next52

- Slice: `yield-sqlite-attach-temp-wal-schema-trigger-current-next52`.
- Behavior: adds `SQLiteAttachTempWalSchemaTriggerPlan`, a bounded native PHP bridge between existing attach/temp trigger WAL yield planning and schema-cache reprepare decisions when trigger body writes target `sqlite_schema` or `sqlite_master`.
- Focused output: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaTriggerCurrentNext52Test.php` passed with `1 test files, 60 assertions, 0 failures`.
- PASS delta: +60 verified focused PASS lines. `lane-status.json` `phpPass` moves from `19277` to `19337`. `benchmarkDenominator.mapped` is unchanged because this reuses already mapped attach/temp trigger, WAL append, and schema-cache surfaces.
- Application smoke: `php lanes/libsqlite/examples/application-attach-temp-wal-schema-trigger-current-next52.php` reports copied `wp_options` trigger schema writes, schema-cookie reprepare schemas, WAL schemas, and dependencies without requiring ext/sqlite.
- Dependency closure: no new support component is needed. The slice reuses `SQLiteAttachTempWalViewTriggerPlan`, `SQLiteAttachTempMainWalSchemaCachePlan`, `SQLiteAttachedSchemaCatalog`, and existing WAL primitives.
- Non-overlap: avoids accepted next48 attach/temp WAL view-trigger planning, next49 attach/temp main WAL schema-cache planning, parser-level JSON table SELECT sources, VFS rollback/sync/write clusters, WAL checkpoint transactions, B-tree page relocation/root collapse/overflow release, SQL expression ORDER BY, GROUP BY text, SELECT subqueries, Unicode GLOB ranges, and batch49 release-runner/status surfaces. The new surface is trigger-caused schema-table writes feeding current/next schema-cookie reprepare state.
