# attach-wal-temp-schema-cache-current-source-next92

Status: focused PHP behavior growth for ATTACH/WAL/temp prepared-statement schema-cache current-source transitions.

This slice adds `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan`, a bounded native PHP planner for prepared statements whose current table source changes after connection-local temp DDL, WAL-backed main schema DDL, `ATTACH`, and `DETACH`. It reports current versus next search order, WAL page-one schema-cookie sources, active-statement current snapshot handling, read retry decisions, write retry blocking, detached-schema expiry, and future table resolution after WAL DDL.

Application smoke:

- `php lanes/libsqlite/examples/application-attach-wal-temp-schema-cache-current-source-next92.php --self-test`
- `application-attach-wal-temp-schema-cache-current-source-next92 self-test passed`

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Test.php`
- Result: `1 test files, 59 assertions, 0 failures`
- PASS lines: `53`

Status delta:

- `lane-status.json` `phpPass` moves from `35916` to `35969`.
- Mapped upstream coverage is unchanged; this is focused PHP behavior coverage over already mapped ATTACH/temp/WAL schema-cache inventory.

Non-overlap:

This avoids accepted ATTACH WAL/temp rollback routing, temp/WAL schema-cache bracket-qualified current-source next88, trigger-cache current-source next89, view/trigger resolution next86, WAL byte materialization, VFS file-control, JSON table/source work, B-tree/pager/pragma batches, and release-runner evidence. The new behavior is prepared-statement source switching across temp shadow creation/drop, attached database detach, newly attached schemas, and WAL page-one schema-cookie changes.

Dependency closure:

No new support component is needed. The slice reuses lane-local schema-cookie, WAL page-one cookie, prepared-statement parsing, and ATTACH/temp schema search-order primitives.

Next task:

Continue with non-overlapping ATTACH parser execution or broader SQL/VFS pager application; avoid another schema-cache wrapper unless it applies actual parser statements or a distinct storage boundary.
