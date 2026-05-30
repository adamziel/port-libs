# SQLite attach TEMP WAL schema cache current-source next161-164

Prepares the attach/TEMP/WAL schema-cache handoff after next157-160:

- next161 covers a TEMP schema write creating `wp_options`, so an unqualified Application options reader moves from `main` to TEMP on reprepare;
- next162 covers `DETACH` of an archive schema expiring a qualified archive terms reader;
- next163 covers a committed WAL schema change that creates `main.wp_comments` and resolves a previously missing qualified reader;
- next164 covers an active attached-schema `INDEXED BY` reader whose current snapshot can finish after the attached index is renamed.

Focused checks:

```text
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext157160Test.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext161164Test.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next157-160.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next161-164.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext157160Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext161164Test.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next157-160.php --self-test
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next161-164.php --self-test
git diff --check
```

Non-overlap: this remains inside the attach schema-cache planner and avoids PRAGMA/VFS/JSON/WAL checkpoint/pager/B-tree behavior. The new surface is specifically current-source prepared statement cache expiry across TEMP shadow creation, DETACH expiry, committed WAL schema table creation, and attached-schema index rename.
