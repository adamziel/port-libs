# real-upstream-corpus-pragma-schema-dynamic-schema6-equivalence-20260530

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema6.test`
  `check_same_database_content` 100, 110, and 120.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema6.test`
  `check_different_database_content` 130.
- Observed through PRAGMA surfaces from upstream `pragma.test` schema-query
  sections and `pragma5.test` `table_list`.

This slice ports the schema6 CREATE TABLE equivalence matrix into generic
PRAGMA schema-catalog assertions. It proves that rowid table forms, redundant
`UNIQUE PRIMARY KEY` forms, and `WITHOUT ROWID` forms preserve stable
`table_info`, `index_list`, `index_xinfo`, and `table_list` metadata when the
dynamic source is `sqlite_schema` SQL text. It also keeps the upstream
different-content rowid versus `WITHOUT ROWID` distinction observable through
`table_list.wr`.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema6EquivalenceTest.php`
- Result: `1 test files, 13443 assertions, 0 failures`.
- PASS cases: 721 focused TestRunner PASS lines.

Non-overlap:

- This is not another accepted `pragma.test` 6.x table-info bulk batch,
  `pragma4` table-valued batch, `pragma5` table-list batch, or `schema5`
  legacy-constraint batch. It specifically owns upstream `schema6.test`
  equivalent/different CREATE TABLE content forms and observes them via
  dynamic PRAGMA schema metadata.
- Mapped denominator coverage remains complete at `1589 / 1589`; this should
  be counted as selected PHP PASS-line/assertion growth only.

Dependency closure:

- No new support component is needed. The slice reuses the lane-local
  `SQLitePragmaSchemaCatalog` and `SQLiteSchemaRecord` dynamic schema metadata
  implementation.
