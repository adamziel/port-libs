# SQLite attach TEMP WAL schema cache current-source next209-212

Extends the ready next205-208 attach/TEMP/WAL schema-cache handoff:

- next209: dropping a TEMP shadow table reveals the main schema target and expires only statements that resolved through the TEMP shadow.
- next210: a committed main WAL page-one schema-cookie frame expires main readers while TEMP and attached readers remain current.
- next211: DETACH removes an attached schema from search order, expires attached readers, and blocks stale attached writers before retry.
- next212: renaming an active TEMP indexed cursor's index lets the current source finish, then returns `SQLITE_SCHEMA` on reset; uncommitted WAL schema-cookie frames are ignored.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext209212Test.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next209-212.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext209212Test.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next209-212.php --self-test
git diff --check
```
