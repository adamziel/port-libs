# SQLite attach TEMP WAL schema cache current-source next157-160

Prepares the next attach/TEMP/WAL schema-cache handoff after next153-156:

- next157 covers dropping a TEMP shadow `wp_postmeta` table so an unqualified WordPress reader resolves to `main` on reprepare;
- next158 covers renaming an attached archive posts table, causing a qualified writer to fail with `SQLITE_SCHEMA` before retry;
- next159 covers `ATTACH` of a network schema resolving a previously detached qualified WordPress options reader;
- next160 covers a current-source `INDEXED BY` reader whose active snapshot can finish after the main schema index disappears.

Focused checks:

```text
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext153156Test.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext157160Test.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next153-156.php
php -l lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next157-160.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext153156Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext157160Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next153-156.php --self-test
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next157-160.php --self-test
git diff --check
```

Non-overlap: this stays inside the attach schema-cache planner and avoids PRAGMA integrity/rootpage, trigger RETURNING/savepoint, pager master-journal, WAL checkpoint reader-pin, VFS locking/file-control, B-tree, JSON, encoding, SELECT planner, and unrelated WAL hot-journal clusters. The new behavior is specifically current-source schema-cache expiry across TEMP shadow removal, attached-schema table rename, new attached-schema resolution, and active current-source `INDEXED BY` expiry after the previous table-drop/index-drop/attach/WAL-cookie slice.
