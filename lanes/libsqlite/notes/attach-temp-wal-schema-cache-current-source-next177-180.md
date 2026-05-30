# SQLite attach TEMP WAL schema cache current-source next177-180

Prepares the attach/TEMP/WAL schema-cache handoff after next173-176:

- next177 covers DETACH of an attached schema while qualified prepared readers expire and active readers finish their current snapshot;
- next178 covers TEMP `DROP INDEX` invalidating an `INDEXED BY` reader and unqualified statements shadowed by the TEMP schema-cookie change;
- next179 covers attached-index rename invalidating an active qualified reader only on reset;
- next180 covers duplicate committed WAL schema events being consolidated before cache expiry.

Focused checks:

```text
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext173176Test.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext177180Test.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next173-176.php
php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next177-180.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext173176Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext177180Test.php
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next173-176.php --self-test
php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next177-180.php --self-test
git diff --check
```

Non-overlap: this stays inside the attach schema-cache planner and avoids PRAGMA/VFS/JSON/WAL checkpoint/pager/B-tree behavior. The new surface is current-source prepared statement cache expiry across attached-schema detach, TEMP index deletion, attached index rename, and duplicate committed WAL event consolidation.
