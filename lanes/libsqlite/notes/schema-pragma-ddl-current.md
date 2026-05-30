# schema-pragma-ddl-current

Status: focused PHP behavior growth for current-connection DDL, schema PRAGMA state, and refreshed schema-catalog PRAGMA rows.

Behavior:

- Added `SQLiteSchemaPragmaDdlCurrent`, an unsuffixed coordinator that applies bounded `sqlite_schema` DDL through `SQLiteSchemaDdlReparsePlan` and updates `SQLitePragmaSchemaDataVersion`.
- Local DDL advances `PRAGMA schema_version` and the file change counter by the number of changed schema operations while preserving same-connection `PRAGMA data_version`, matching SQLite's connection-local data-version behavior.
- The result reports before/after PRAGMA rows, header state, invalidated prepared statements, refreshed `table_xinfo` / `index_list` samples, and dependency evidence.
- Added a Application smoke for copied `wp_options` schema migration reprepare after table rename plus partial-index creation.

Verification:

```
php -l lanes/libsqlite/src/SQLiteSchemaPragmaDdlCurrent.php
php -l lanes/libsqlite/tests/SQLiteSchemaPragmaDdlCurrentTest.php
php -l lanes/libsqlite/examples/application-schema-pragma-ddl-current.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaPragmaDdlCurrentTest.php
php lanes/libsqlite/examples/application-schema-pragma-ddl-current.php --self-test
git diff --check -- lanes/libsqlite
```

Non-overlap:

This avoids numbered duplicate production classes and does not repeat accepted standalone schema DDL reparse, schema rename view/trigger rewrites, generated-column/index reparse, PRAGMA schema/data-version primitives, attach/temp schema-cache invalidation, WAL/VFS/B-tree/JSON/SELECT clusters, or suite evidence. The new surface is specifically the current-connection composition of DDL with schema PRAGMA/header state and refreshed PRAGMA catalog rows.

Dependency closure:

No new support component is needed. The slice reuses lane-local schema DDL reparse, PRAGMA schema catalog, PRAGMA schema/data-version state, and existing ALTER TABLE tokenizer helpers.
