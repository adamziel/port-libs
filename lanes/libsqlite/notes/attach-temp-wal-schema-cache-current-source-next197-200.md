# SQLite attach TEMP WAL schema cache current-source next197-200

Extends the ready next193-196 attach/TEMP/WAL schema-cache handoff:

- next197: a main `INDEXED BY` rename lets an active current-source cursor finish, then returns `SQLITE_SCHEMA` on reset.
- next198: TEMP schema writes bump the temp schema cookie and can shadow an unqualified `wp_options` reader before the next step.
- next199: DETACH removes an attached archive schema from search order and blocks stale archive writers before retry.
- next200: committed WAL page-one schema-cookie frames expire matching main-schema readers while uncommitted WAL frames remain ignored.

The source wrapper records dependencies on next197-200 plus the ready next193-196 and next189-192 handoffs while reusing the shared attach schema-cache transition engine.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext197200Test.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next197-200.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext197200Test.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next197-200.php --self-test
git diff --check
```
