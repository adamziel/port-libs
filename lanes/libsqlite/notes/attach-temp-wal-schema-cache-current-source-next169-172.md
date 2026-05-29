# SQLite attach TEMP WAL schema cache current-source next169-172

Prepares the attach/TEMP/WAL schema-cache handoff after next165-168:

- next169 covers an attached-schema `DROP INDEX` expiring an active `INDEXED BY` reader;
- next170 covers an attached WAL schema-cookie commit making a previously missing qualified table visible;
- next171 covers a TEMP table drop exposing the main table again for unqualified lookup;
- next172 covers `DETACH` expiry for active attached readers and write statements.

Focused checks:

```text
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext165168Test.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext169172Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next165-168.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next169-172.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext165168Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext169172Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next165-168.php --self-test
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next169-172.php --self-test
git diff --check
```

Non-overlap: this stays inside the attach schema-cache planner and avoids PRAGMA/VFS/JSON/WAL checkpoint/pager/B-tree behavior. The new surface is current-source prepared statement cache expiry across attached index removal, attached WAL table visibility, TEMP shadow removal, and attached database detach.
