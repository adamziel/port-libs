# attach-wal-temp-current-next64

- Scope: bounded ATTACH/temp/main WAL schema-cache behavior for current-vs-next object resolution. This covers prepared table names whose current snapshot resolves through temp/main, while committed WAL DDL changes next table/index visibility before statement reuse.
- Non-overlap: avoids accepted ATTACH temp/main WAL schema-cookie cache current-next49, SQL extraction current-next53, temp/main WAL collation cache current-next55, trigger/view/cache invalidation, WAL checkpoint/release/savepoint, JSON/schema/import WAL savepoint, and queued attach/temp WAL schema-cache surfaces. This slice adds object-list resolution changes rather than another schema-cookie-only or trigger route plan.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachWalTempCurrentNext64Test.php` passed with `1 test files, 56 assertions, 0 failures`.
- Application smoke: `php lanes/libsqlite/examples/application-attach-wal-temp-current-next64.php --self-test` validates copied `wp_options` import behavior where temp shadows main in the current snapshot, WAL DDL removes current temp/main objects, attached archive creates `wp_options`, and reprepare is required.
- Dashboard delta: `phpPass` moves from `23341` to `23397` for the 56 verified PASS lines. Mapped focused coverage moves from `450` to `451` for the newly named attach/WAL current-next object-resolution unit.
- Dependency closure: no new support component is needed; the patch reuses existing bounded attach schema-cache, WAL schema-cookie, and temp/main name-resolution components.
