# SQLite attach TEMP WAL schema cache current-source next189-192

Prepares the attach/TEMP/WAL schema-cache handoff after next185-188:

- next189: active TEMP table rename keeps the current-source cursor usable until reset, then forces `SQLITE_SCHEMA`.
- next190: attached schema index removal expires indexed readers while keeping table resolution retryable.
- next191: main WAL schema-cookie advancement expires qualified and unqualified main readers without disturbing TEMP/archive statements.
- next192: attached database detach blocks writers before retry while read statements can reprepare against the new schema map.

The source wrapper records dependencies on next189-192 and the prior next185-188 handoff, while reusing the existing schema-cache transition engine.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext189192Test.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next189-192.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext189192Test.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next189-192.php --self-test
git diff --check
```
