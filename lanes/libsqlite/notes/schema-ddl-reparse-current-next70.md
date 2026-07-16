# Schema DDL Reparse Current Next70

- Behavior: `SQLiteSchemaDdlReparsePlan` now applies bounded `CREATE VIEW`, `CREATE TRIGGER`, `DROP VIEW`, and `DROP TRIGGER` schema-record mutations, including rootpage-zero records, trigger target extraction, schema-cookie increments, and prepared-statement invalidation after Application migration DDL.
- DROP TABLE now removes table-owned triggers together with the table and indexes while leaving dependent views in the schema catalog for later reparse/error handling, matching SQLite's catalog mutation shape more closely than the earlier table/index-only slice.
- Application smoke: `examples/application-schema-ddl-reparse-current-next70.php` previews copied `wp_options` autoload view/trigger creation and stale prepared statement invalidation without requiring ext/sqlite.
- Non-overlap: avoids accepted current-next56 schema table/index reparse, ALTER ADD/DROP COLUMN, ALTER rename trigger/view rewriting, view/trigger metadata-only corpus, JSON/WAL/B-tree/VFS accepted clusters, and batch68 ATTACH/JSONB/LIKE/recursive/VFS/WAL surfaces.
- Dependency closure: no new support component is needed; this reuses existing `SQLiteSchemaRecord` and `SQLitePragmaSchemaCatalog` lane primitives.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaDdlReparseCurrentNext70Test.php
```

Expected PASS lines: 6.
