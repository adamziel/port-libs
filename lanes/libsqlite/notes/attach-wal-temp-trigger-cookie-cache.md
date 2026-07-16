# Attach WAL Temp Trigger Cookie Cache

Status: focused PHP behavior growth for ATTACH/WAL/temp trigger program cache expiry at the current-source/next-source boundary.

Behavior:

- Added `SQLiteAttachTempWalSchemaTriggerPlan::walTriggerCookieCachePlan()`.
- Covers prepared trigger programs whose source, target, or trigger-body dependency schema cookie changes through committed WAL page-1 frames or explicit WAL/temp schema-cookie state even when the PHP schema-record catalog is otherwise unchanged.
- Active trigger programs return `SQLITE_OK` for the current source and are marked for `SQLITE_SCHEMA` on reset/reprepare; inactive expired trigger programs report `SQLITE_SCHEMA` before the next trigger step.
- Temp triggers that target `main` expire when either their temp trigger/audit schema changes or their main target/body dependency schema changes; unrelated attached-schema WAL cookies do not expire main trigger programs.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachWalTempTriggerCookieCacheTest.php`
- Result: `1 test files, 65 assertions, 0 failures` with 65 PASS lines.
- Application smoke: `php lanes/libsqlite/examples/application-attach-wal-temp-trigger-cookie-cache.php --self-test`
- Result: `application-attach-wal-temp-trigger-cookie-cache self-test passed`

Dashboard delta:

- `lane-status.json` `phpPass` moves from `34719` to `34784` for this patch's verified focused PASS-line delta.
- `benchmarkDenominator.mapped` is unchanged; this reuses accepted ATTACH/WAL/temp trigger-cache inventory rather than claiming a new upstream unit.

Non-overlap:

This avoids accepted ATTACH WAL/temp rollback routing, schema-cache invalidation, temp schema trigger cache trigger cache reprepare, WAL/temp schema-cookie next87, temp/WAL view-trigger routing, VFS file-control/write/sync/lock behavior, JSON table/source work, B-tree page/freelist/overflow clusters, SELECT SQL text/group/order/subquery clusters, and WAL checkpoint/savepoint reader-pin behavior. The new surface is trigger-program expiry caused by WAL/temp schema-cookie changes when schema records are unchanged, with active-current-source continuation versus next-step `SQLITE_SCHEMA`.

Dependency closure:

No new support component is needed. The slice reuses lane-local attached schema catalogs, trigger resolution, schema-cookie extraction from WAL page-1 metadata, and existing trigger current-source reset semantics.

Next task:

Continue with broader ATTACH prepared statement expiry only if it covers a distinct non-trigger statement class; otherwise prioritize non-overlapping SQL executor/planner, WAL/pager durability, B-tree, JSON planner, encoding/collation, or suite-countability gaps.
