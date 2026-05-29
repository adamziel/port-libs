# Attach Temp WAL Trigger View Cache Reprepare

Status: focused PHP behavior growth for bracket-quoted attached schema sources in prepared trigger/view cache current-source checks.

This slice makes the trigger and view cache current-source planners normalize bracket-quoted schema names such as `[site]` the same way they already normalize `"site"` and backtick-quoted forms. That keeps copied WordPress export SQL with bracket-quoted attached databases from comparing WAL schema cookies under a stale literal `[site]` source.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalTriggerViewCacheReprepareTest.php`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-trigger-view-cache-reprepare.php --self-test`
- `php -l lanes/libsqlite/src/SQLiteAttachTempWalSchemaTriggerPlan.php`
- `php -l lanes/libsqlite/src/SQLiteAttachWalTempViewCachePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalTriggerViewCacheReprepareTest.php`
- `php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-trigger-view-cache-reprepare.php`
- `git diff --check -- lanes/libsqlite`

Dashboard delta: focused PHP test adds 56 verified PASS lines. `phpPass` should move from `35916` to `35972` when accepted. Mapped upstream coverage is unchanged because this is a lane-local current-source behavior on the already mapped ATTACH/temp/WAL trigger/view cache surface, not a newly hydrated upstream inventory row.

Non-overlap: avoids accepted ATTACH WAL/temp rollback routing, bracket-quoted SQL table extraction, trigger cache trigger cookie cache, view-trigger source comparison, VFS/WAL writer/sync/lock clusters, B-tree overflow/page-move clusters, JSON table/source/constraint work, and encoding Unicode GLOB. The new behavior is only bracket-quoted schema source normalization for prepared trigger/view cache current-source state and WAL schema-cookie keys.

Dependency closure: no new support component is needed. The slice reuses the lane-local attached schema catalog, trigger/view resolution, schema-cookie, and WAL current-source cache planners.

Next task: wire the same quoted-source normalization into any future unified prepared-statement cache path that combines table, view, and trigger invalidation in one executor-level statement object.
