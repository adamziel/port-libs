# SQLite attach TEMP WAL schema cache current-source next181-184

Prepares the attach/TEMP/WAL schema-cache handoff after next177-180:

- next181 covers `ATTACH` of a new media schema resolving a previously detached qualified prepared reader;
- next182 covers attached `DROP TABLE` expiring a qualified `INDEXED BY` reader even when the index name still exists in the schema cache;
- next183 covers TEMP table rename invalidating active and unqualified readers while active cursors finish their current source before reset;
- next184 covers an uncommitted TEMP WAL schema event being filtered before cache expiry.

Focused checks:

```text
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext177180Test.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext181184Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next177-180.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next181-184.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext177180Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext181184Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next177-180.php --self-test
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next181-184.php --self-test
git diff --check
```

Non-overlap: this stays inside the attach schema-cache planner and avoids PRAGMA/VFS/JSON/WAL checkpoint/pager/B-tree behavior. The new surface is current-source prepared statement cache expiry across new schema attach, attached table deletion, TEMP table rename, and uncommitted TEMP WAL event filtering.
