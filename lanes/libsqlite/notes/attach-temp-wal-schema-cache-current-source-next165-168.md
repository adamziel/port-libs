# SQLite attach TEMP WAL schema cache current-source next165-168

Prepares the attach/TEMP/WAL schema-cache handoff after next161-164:

- next165 covers a TEMP `CREATE INDEX` making an active `INDEXED BY` reader valid only after reset;
- next166 covers a main-schema table drop blocking a write statement before retry;
- next167 covers `ATTACH` of a network database resolving a previously missing qualified reader;
- next168 covers a main table rename expiring unqualified readers while unrelated qualified attached readers stay stable.

Focused checks:

```text
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext161164Test.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext165168Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next161-164.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next165-168.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext161164Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext165168Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next161-164.php --self-test
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next165-168.php --self-test
git diff --check
```

Non-overlap: this remains inside the attach schema-cache planner and avoids PRAGMA/VFS/JSON/WAL checkpoint/pager/B-tree behavior. The new surface is specifically current-source prepared statement cache expiry across TEMP index creation, main table drop writer blocking, attached-schema resolution of a qualified reader, and table rename expiry with stable qualified attached readers.
