# SQLite attach TEMP WAL schema cache current-source next813-828

Extends the next797-812 attach/TEMP/WAL schema-cache current-source handoff in `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext813828()`.

- next813-828 keeps the same consolidated attach schema-cache planner and records the next dependency range before the next797-812 predecessor markers.
- The focused fixture covers committed main WAL schema-cookie movement through next828, TEMP schema writes, attached index rename expiry, attached table drop/rename expiry, attached schema publication, and detached-schema removal.
- The detached scratch handoff remains stable when an attached schema is added, receives only an uncommitted WAL frame, and is detached before it can affect current-source lookup.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext813828Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next813-828.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext797812Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext813828Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next813-828.php --self-test
git diff --check
```

Non-overlap: this stays inside attach/TEMP/WAL schema-cache current-source coverage and avoids PRAGMA, JSON, B-tree, VFS, planner, row-value, and unrelated WAL hot-journal surfaces.
