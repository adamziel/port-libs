# SQLite attach TEMP WAL schema cache current-source next221-228

Extends the ready next213-220 attach/TEMP/WAL schema-cache handoff as the next larger follow-on:

- next221: dropping a TEMP import table expires TEMP-shadow readers and lets an active current-source cursor finish before reset.
- next222: renaming a main `INDEXED BY` target expires main readers while preserving the active current-source step result.
- next223: attaching a cache database appends search order without invalidating existing prepared statements.
- next224: a committed attached analytics WAL schema-cookie frame expires attached readers and blocks attached writers before retry.
- next225: an uncommitted TEMP WAL schema-cookie frame is ignored and does not advance schema-cache invalidation.
- next226: DETACH of the reporting schema removes explicit attached readers from the next source.
- next227: creating a TEMP index advances only TEMP schema-cookie state and leaves explicit main readers current.
- next228: dropping a main terms table expires main readers without disturbing TEMP shadow or attached analytics readers.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext221228Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next221-228.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext221228Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next221-228.php --self-test
git diff --check
```
