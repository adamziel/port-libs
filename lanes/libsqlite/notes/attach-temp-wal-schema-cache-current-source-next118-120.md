# SQLite attach TEMP WAL schema cache current-source next118-120

Prepares the attach/TEMP/WAL schema-cache handoff after next117 duplicate consolidation:

- next118 ignores rolled-back `wal_commit` rows so page-one cookies from uncommitted frames do not expire current-source statements;
- next119 ignores rolled-back TEMP schema writes while still consolidating committed WAL/schema-write duplicates once;
- next120 preserves real `ATTACH` and index DDL handoff changes after the rollback filter, so future attached schemas and `INDEXED BY` changes still force reprepare.

Focused checks:

```text
php lanes/libsqlite/tests/run.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext117Test.php
php lanes/libsqlite/tests/run.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext118120Test.php
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next117.php --self-test
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next118-120.php --self-test
```
