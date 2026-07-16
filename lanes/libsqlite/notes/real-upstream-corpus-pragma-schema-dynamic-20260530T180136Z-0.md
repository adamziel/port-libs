# real-upstream-corpus-pragma-schema-dynamic-20260530T180136Z-0

Base accepted HEAD: `f66597de21a7c168178b6eec67c6e12b5daf324d`.

Added a generic real-upstream PRAGMA/schema dynamic invalidation batch in
`SQLiteRealUpstreamPragmaSchemaDynamicInvalidationTest.php`.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test`
  - `schema-9.1`: dropped table invalidates other connection lookups.
  - `schema-9.2`: dropped view invalidates other connection lookups.
  - `schema-12.1`: rollback of DDL expires prepared schema state even when the
    schema cookie value is reused by later DDL.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-6.1`: `PRAGMA database_list` and schema-qualified catalog lookup.
  - `pragma-8.1`: schema-version isolation per attached schema.
  - `pragma-8.2`: user-version isolation per attached schema.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma3.test`
  - `pragma3-100..190`: data-version read-only assignment and external commit
    observer behavior.

Focused result:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicInvalidationTest.php
1 test files, 3900 assertions, 0 failures
```

The file contributes 530 distinct TestRunner PASS cases:

- 90 dropped-table schema-cache invalidation variants.
- 90 dropped-view schema-cache invalidation variants.
- 90 DDL rollback/schema-expiry variants.
- 90 database-list plus schema-qualified PRAGMA variants.
- 85 schema/user-version isolation variants.
- 85 data-version external observer variants.

Non-overlap:

This does not repeat accepted PRAGMA table-info/default-comment coverage,
`pragma4` table-valued schema argument coverage, or existing generic
schema/version follow-up rows. It focuses on schema-cache invalidation and
attached-schema version isolation using generic `app_record`,
`session_record`, and `archive_record` names.

Dependency closure:

No new support component is needed; this reuses lane-local
`SQLiteAttachedSchemaCatalog`, `SQLitePragmaSchemaDataVersion`, and
`SQLiteSchemaRecord`.
