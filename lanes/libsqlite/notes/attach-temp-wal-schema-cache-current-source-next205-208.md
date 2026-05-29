# SQLite attach TEMP WAL schema cache current-source next205-208

Extends the ready next201-204 attach/TEMP/WAL schema-cache handoff:

- next205: dropping a TEMP shadow table reveals the main schema target and expires only statements that resolved through the TEMP shadow.
- next206: a committed main WAL page-one schema-cookie frame expires main readers while TEMP and attached readers remain current.
- next207: DETACH removes an attached schema from search order, expires attached readers, and blocks stale attached writers before retry.
- next208: renaming an active TEMP indexed cursor's index lets the current source finish, then returns `SQLITE_SCHEMA` on reset; uncommitted WAL schema-cookie frames are ignored.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext205208Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next205-208.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext205208Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next205-208.php --self-test
git diff --check
```
