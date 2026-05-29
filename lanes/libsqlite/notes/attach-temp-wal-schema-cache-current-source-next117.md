# SQLite attach TEMP WAL schema cache current-source next117

Consolidates duplicate attach schema-cache DDL handoff rows before reprepare planning:

- repeated `drop_index`, `create_index`, `drop_table`, `schema_write`, and `wal_commit` rows with the same schema/object advance the schema cookie once;
- distinct index/table events remain ordered and still expire prepared statements whose table or `INDEXED BY` resolution changes;
- next117 depends on next116 indexed-schema cache expiry and preserves current-source WAL page-one cookie handling.

Focused checks:

```text
php lanes/libsqlite/tests/run.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext117Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next117.php --self-test
```
