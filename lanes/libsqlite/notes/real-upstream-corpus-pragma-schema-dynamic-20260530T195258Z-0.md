# real-upstream-corpus-pragma-schema-dynamic-20260530T195258Z-0

Implemented a real upstream pragma/schema corpus batch from SQLite upstream:

- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema5.test`
- Upstream scenarios: `schema5-1.1` through `schema5-1.7`
- Ported behavior: legacy `CREATE TABLE` constraint syntax remains readable
  through sqlite_schema and yields stable `PRAGMA table_info` metadata even
  when old schema SQL uses adjacent table constraints without ordinary comma
  separation.

Focused coverage:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema5LegacyTest.php`
- 1,000 distinct TestRunner PASS cases
- 6,000 focused behavior assertions

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema5LegacyTest.php
```

Result:

```text
1 test files, 6000 assertions, 0 failures
```

Non-overlap:

- This does not repeat prior `pragma.test` table-info/index-info/foreign-key,
  `pragma3.test` data-version, `pragma4.test` table-valued PRAGMA,
  `pragma5.test` introspection-table, schema2/schema3 invalidation, wide-batch,
  thousand-row, or pager-state coverage.
- It targets the separate upstream `schema5.test` legacy CREATE TABLE grammar
  compatibility surface and verifies the existing generic schema catalog path.

Dependency closure:

- No new support component is needed. The batch reuses the existing
  `SQLitePragmaSchemaCatalog` and `SQLiteSchemaRecord` parser/catalog
  primitives.
