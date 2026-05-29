# SQLite attach TEMP WAL schema cache current-source next213-220

Extends the ready next209-212 attach/TEMP/WAL schema-cache handoff as a larger follow-on:

- next213: renaming an attached analytics index expires only statements pinned to that attached schema index.
- next214: a committed TEMP schema write expires TEMP readers while main and attached readers remain current.
- next215: attaching a new reporting database updates search order without expiring existing prepared statements.
- next216: renaming a main table expires explicit and unqualified main readers while a TEMP shadow reader remains current.
- next217: an uncommitted WAL schema-cookie frame is ignored and does not advance schema-cache invalidation.
- next218: dropping an active TEMP cursor's index lets the current source finish, then returns `SQLITE_SCHEMA` on reset.
- next219: DETACH of an attached archive schema expires attached readers and blocks stale attached writers before retry.
- next220: a committed main WAL page-one schema-cookie frame with new index inventory expires only main schema readers.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext213220Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next213-220.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext213220Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next213-220.php --self-test
git diff --check
```
