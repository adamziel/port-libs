# real-upstream-corpus-pragma-schema-dynamic-20260531T060139Z-0

Base accepted HEAD: `5a0bbcc53e4d53b976a73e07fed57fd92e934f80`.

Implemented a real upstream PRAGMA/schema dynamic behavior cluster from the
hydrated SQLite upstream checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema4.test`
- `schema4-1.1` through `schema4-1.8`: triggers share names with tables,
  views, indexes, and virtual tables without being dropped or disabled when
  same-name non-trigger objects are dropped and recreated.
- `schema4-2.1` through `schema4-2.11`: table renames preserve same-name
  trigger target binding, and temp schema objects with colliding names keep
  their own `sqlite_temp_schema` SQL text.

Lane changes:

- Added `SQLiteSchemaObjectNamespacePlan` as a generic SQLite schema namespace
  and trigger-dispatch model.
- Added `SQLiteRealUpstreamCorpusPragmaSchemaDynamicObjectNamespace20260531Test.php`
  with 1,000 generated upstream-derived behavior cases plus one source
  citation/dependency-closure case.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicObjectNamespace20260531Test.php`
- Result: `1 test files, 8507 assertions, 0 failures`
- PASS-line growth: `+1001` focused TestRunner PASS cases.

Non-overlap:

- This slice does not repeat existing pragma data-version, schema-version,
  prepared-expiry, pragma5 virtual-row, page-count, table-info, index-list, or
  schema2 active-runtime busy coverage.
- It specifically owns `schema4.test` object namespace and temp schema rename
  behavior.

Dependency closure:

- No new support component needed; reuses lane-local schema catalog and
  trigger-dispatch modeling.
