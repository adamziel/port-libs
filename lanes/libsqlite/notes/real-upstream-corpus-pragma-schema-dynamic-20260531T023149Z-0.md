# real-upstream-corpus-pragma-schema-dynamic-20260531T023149Z-0

Source truth: hydrated upstream SQLite checkout under
`/home/claude/port-libs/.upstream-cache/libsqlite/test`.

This slice adds `SQLiteRealUpstreamPragmaSchemaDynamicRemainderTest.php`, a
non-overlapping dynamic attached-schema/table-valued PRAGMA corpus batch:

- `pragma.test` `pragma-6.1`: `PRAGMA database_list` ordering for main, temp,
  and attached databases.
- `pragma.test` `pragma-6.2`: schema-qualified and unqualified
  `PRAGMA table_info` rows, including TEMP shadowing over main.
- `pragma.test` `pragma-6.3`: `PRAGMA foreign_key_list` row shape and action
  ordering.
- `pragma.test` `pragma-6.4`: `PRAGMA index_list` origin and uniqueness
  metadata.
- `pragma.test` `pragma-6.5`: `PRAGMA index_info` / `index_xinfo` key column,
  collation, descending-term, and auxiliary row behavior.
- `pragma4.test` `4.1` through `4.5`: table-valued `pragma_*()` lookup across
  main and attached schemas plus empty rowsets for stale/missing objects.

Focused coverage:

- 1,000 distinct TestRunner PASS cases.
- 24,000 behavior assertions.
- Generic application table names only: `app_schema_settings_*`,
  `tenant_schema_settings_*`, `tenant_schema_parent_*`, and attached schemas
  named `tenant*`.

Non-overlap:

- Does not repeat prior `SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php`
  primary-key ordinal/default/comment/table-list dynamic cases.
- Does not repeat
  `SQLiteRealUpstreamPragmaSchemaDynamicFollowupTest.php` schema-version,
  data-version, generated-column, duplicate-PK, and base `pragma-6.2` through
  `pragma-7.1.2` follow-up cases.
- Does not add metadata-only upstream admission rows, fake `.test` names, or
  domain-specific libsqlite APIs.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicRemainderTest.php`
  -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicRemainderTest.php`
  -> `1 test files, 24000 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`
  -> clean.

Dependency closure: no new support component is needed. This reuses the
existing lane-local `SQLiteAttachedSchemaCatalog`, `SQLitePragmaSchemaCatalog`,
and `SQLiteSchemaRecord` schema introspection helpers.
