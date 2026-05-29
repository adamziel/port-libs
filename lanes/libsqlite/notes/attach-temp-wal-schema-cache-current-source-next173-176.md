# SQLite attach TEMP WAL schema cache current-source next173-176

Prepares the attach/TEMP/WAL schema-cache handoff after next169-172:

- next173 covers an attached WAL commit making a previously missing qualified table visible;
- next174 covers a TEMP `CREATE INDEX` resolving an `INDEXED BY` reader while expiring unqualified statements shadowed by TEMP schema-cookie churn;
- next175 covers an attached table rename while an active reader continues on the current snapshot until reset;
- next176 covers rolled-back WAL schema events being filtered before cache expiry.

Focused checks:

```text
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext165168Test.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext169172Test.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext173176Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next165-168.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next169-172.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next173-176.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext165168Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext169172Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext173176Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next165-168.php --self-test
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next169-172.php --self-test
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next173-176.php --self-test
git diff --check
```

Non-overlap: this stays inside the attach schema-cache planner and avoids PRAGMA/VFS/JSON/WAL checkpoint/pager/B-tree behavior. The new surface is current-source prepared statement cache expiry across attached WAL table visibility, TEMP index creation, attached table rename, and rollback-filtered WAL events.
