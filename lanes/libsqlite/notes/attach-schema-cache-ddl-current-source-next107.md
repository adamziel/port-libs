# attach-schema-cache-ddl-current-source-next107

Behavior: `SQLiteAttachedSchemaCatalog::applySchemaDdlCurrentSource()` applies bounded DDL to one attached schema through `SQLiteSchemaDdlReparsePlan`, updates that schema's records only when the DDL changes sqlite_schema, invalidates the connection lookup cache, and reports current-source resolution changes for prepared table/index cache snapshots.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteAttachedSchemaCatalog.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachSchemaCacheDdlCurrentSourceNext107Test.php`
- `php -l lanes/libsqlite/examples/application-attach-schema-cache-ddl-current-source-next107.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachSchemaCacheDdlCurrentSourceNext107Test.php`
- `php lanes/libsqlite/examples/application-attach-schema-cache-ddl-current-source-next107.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: +4 focused libsqlite PASS lines after integration; this lane updates `phpPass` from 41873 to 41877 based on the focused PASS-line delta from the new test file.

Dependency closure: no new support component is required. This reuses the existing attached schema catalog, schema DDL reparse, schema-cookie, and pragma schema catalog components.

Non-overlap: avoids accepted batch104/105 ATTACH temp/WAL schema-trigger reprepare, schema-cookie reprepare, view/trigger routing, temp/WAL cache routing, and DDL reparse standalone surfaces. The new boundary is catalog-level current-source DDL application for one attached schema with prepared lookup cache invalidation and current/next resolution reporting.
