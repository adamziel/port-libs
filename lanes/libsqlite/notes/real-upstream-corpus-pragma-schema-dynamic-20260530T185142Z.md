# Real Upstream PRAGMA/Schema Dynamic Corpus

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T184906Z-0`
Base accepted HEAD: `7e63d4798cb030955a466f3272d59cba9c03648e`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema4.test`
  - `schema4-1.*`: dropping and creating triggers and other schema objects where dependent table/index/trigger records must be removed or refreshed.
  - `schema4-2.*`: renaming tables where triggers/views/indexes referencing the old table name must be reparsed.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema3.test`
  - Dynamic schema-cache invalidation after schema-array changes, represented here by ATTACH/DETACH plus table-valued PRAGMA resolution.
- Existing file context also cites `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`, `pragma4.test`, and `pragma5.test` for table_info/table_xinfo/index_list/index_xinfo/foreign_key_list/function_list/module_list behavior.

Patch:

- Extended `lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php`.
- Added 1,336 new distinct TestRunner PASS cases:
  - 334 drop-table cases that verify dependent trigger/index removal, schema-cookie expiry, prepared statement invalidation, and resolution-cache changes.
  - 334 drop/create trigger cases that verify schema-cookie increments and current trigger SQL replacement.
  - 334 rename-table cases that verify trigger/view/index reparse and resolution-cache changes.
  - 334 attach/detach cases that verify dynamic `pragma_table_info()` schema resolution and schema-cache invalidation across database-list changes.
- Added at least 7,684 new behavior assertions in the new cases, above the real-corpus floor.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php`
  - `1 test files, 13451 assertions, 0 failures`

Non-overlap:

- This extends the existing PRAGMA/schema dynamic corpus into upstream `schema4.test` drop/create/rename object behavior and `schema3.test` ATTACH/DETACH schema-cache invalidation.
- It does not repeat the previously accepted PRAGMA schema2 temp coverage, PRAGMA data_version/schema invalidation, or metadata-only runner admission rows.

Dependency closure:

- No new support component is needed. The batch reuses existing native PHP `SQLiteAttachedSchemaCatalog`, `SQLiteSchemaDdlReparsePlan`, `SQLitePragmaSchemaCatalog`, and `SQLiteSchemaRecord` behavior.
