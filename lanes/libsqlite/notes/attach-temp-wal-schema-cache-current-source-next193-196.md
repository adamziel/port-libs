# SQLite attach TEMP WAL schema cache current-source next193-196

Extends the attach/TEMP/WAL schema-cache handoff after next189-192:

- next193: TEMP `INDEXED BY` rename lets an active current-source cursor finish, then reports `SQLITE_SCHEMA` on reset.
- next194: a new attached reporting schema enters search order without expiring statements that resolved before attach.
- next195: attached schema index creation bumps the schema cookie and blocks attached writers before retry.
- next196: uncommitted WAL page-one schema-cookie frames are ignored until a committed schema event exists.

The source wrapper records dependencies on next193-196 and the ready next189-192 handoff while reusing the shared transition engine.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext193196Test.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next193-196.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext193196Test.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next193-196.php --self-test
git diff --check
```
