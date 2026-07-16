# Attach Temp WAL Schema Trigger Body Dependency Reprepare

Status: focused PHP behavior growth for prepared trigger reprepare decisions across ATTACH, TEMP schema shadowing, and WAL schema-cookie current/next boundaries.

This slice adds `SQLiteAttachTempWalSchemaTriggerPlan::triggerBodyDependencyRepreparePlan()`. It extends the accepted next90 trigger current-source comparison by resolving trigger-body table dependencies against the current and next schema catalogs. A prepared TEMP trigger whose body writes an unqualified `wp_option_audit` table now records that the current source resolved to `main.wp_option_audit`, while the next source resolves to `temp.wp_option_audit` after TEMP DDL. Active triggers finish the current source and report `SQLITE_SCHEMA` on reset; inactive triggers report `SQLITE_SCHEMA` before the next trigger-body step. Non-TEMP triggers keep unqualified body dependencies scoped to their trigger schema, and explicitly qualified attached dependencies remain stable.

Application path: `application-attach-temp-wal-schema-trigger-body-dependency-reprepare.php` models copied `wp_options` import triggers where a plugin creates a TEMP audit table while WAL schema cookies also advance. The smoke proves the TEMP trigger body must reprepare because its unqualified audit write changes source, while the attached site trigger remains reusable.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteAttachTempWalSchemaTriggerPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaTriggerBodyDependencyReprepareTest.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-trigger-body-dependency-reprepare.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaTriggerBodyDependencyReprepareTest.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-trigger-body-dependency-reprepare.php --self-test
git diff --check -- lanes/libsqlite
```

Dashboard delta: update `phpPass` by the focused PASS-line delta verified for this test file. `benchmarkDenominator.mapped` is unchanged; this is additional current-source PHP behavior over the already mapped ATTACH/temp/WAL schema-trigger reprepare surface, not a newly hydrated upstream Tcl inventory unit.

Non-overlap: avoids accepted ATTACH schema-cookie CTE extraction, bracket-quoted schema cache extraction, trigger/view cache current-source comparisons, generic trigger source invalidation, SQL attach/detach lifecycle, VFS/write/sync/lock clusters, WAL checkpoint/savepoint byte paths, B-tree page-move/freelist/overflow clusters, JSON table/source/constraint work, and encoding Unicode GLOB. The new surface is resolved trigger-body dependency source movement for prepared TEMP triggers at the current-source to next-source boundary.

Dependency closure: no new support component is needed. The slice reuses the lane-local attached schema catalog, trigger source resolution, schema-cookie, WAL, and TEMP/main search-order primitives.

Next task: wire the resolved body-dependency snapshots into a broader native prepared-statement executor once trigger bytecode execution owns catalog invalidation directly.
