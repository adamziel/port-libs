# SQLite attach TEMP WAL schema cache current-source next989-1004

Extends the next973-988 attach/TEMP/WAL schema-cache current-source handoff in `SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext9891004()`.

- next989-1004 keeps the consolidated attach schema-cache planner and records the new dependency range before the next973-988 predecessor markers.
- The focused fixture covers committed main WAL schema-cookie movement through next1004, TEMP schema writes, attached index rename expiry, attached table drop expiry, detached review schema removal, attached seal schema visibility, publish WAL movement, uncommitted WAL filtering, and stable handoff metadata lookup preservation.

Validation:

```bash
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext973988Test.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext9891004Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next973-988.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next989-1004.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext973988Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext9891004Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next973-988.php --self-test
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next989-1004.php --self-test
git diff --check
```
