# SQLite attach TEMP WAL schema cache current-source next201-204

Extends the ready next197-200 attach/TEMP/WAL schema-cache handoff:

- next201: ATTACH adds an independent schema to search order without expiring prepared statements that resolve to existing schemas.
- next202: dropping a TEMP table bumps only the temp schema cookie and expires prepared statements that resolved through temp.
- next203: rebuilding an attached-schema index expires archive readers and blocks stale archive writers before retry.
- next204: a main table rename lets an active current-source cursor finish, then returns `SQLITE_SCHEMA` on reset; uncommitted WAL schema-cookie frames are ignored.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext201204Test.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next201-204.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext201204Test.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next201-204.php --self-test
git diff --check
```
