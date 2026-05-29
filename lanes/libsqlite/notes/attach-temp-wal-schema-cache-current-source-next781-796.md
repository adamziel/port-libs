# SQLite attach TEMP WAL schema cache current-source next781-796

Extends the next765-780 attach/TEMP/WAL schema-cache current-source handoff in `SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext781796()`.

- next781-796 keeps the same consolidated attach schema-cache planner and records the next dependency range before the next765-780 predecessor markers.
- The focused fixture covers committed main WAL schema-cookie movement through next796, TEMP schema writes, attached index rename expiry, attached table drop/rename expiry, attached schema publication, and detached-schema removal.
- The detached scratch handoff remains stable when an attached schema is added, receives only an uncommitted WAL frame, and is detached before it can affect current-source lookup.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext781796Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next781-796.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext765780Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext781796Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next781-796.php --self-test
git diff --check
```

Non-overlap: this stays inside attach/TEMP/WAL schema-cache current-source coverage and avoids PRAGMA, JSON, B-tree, VFS, planner, row-value, and unrelated WAL hot-journal surfaces.
