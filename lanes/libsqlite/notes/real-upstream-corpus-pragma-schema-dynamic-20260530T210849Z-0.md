# real-upstream-corpus-pragma-schema-dynamic-20260530T210849Z-0

Base accepted HEAD: `140c9861a340b8e75fdc8ea93863883edb030323`.

Added `SQLiteRealUpstreamPragmaSchemaDynamicFourthThousandTest.php` with 1001
focused TestRunner PASS cases and 8403 behavior assertions.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  sections `4.1.1` through `4.5.5`: schema PRAGMAs and table-valued PRAGMA
  functions across main/temp/attached schemas, plus invalidation after schema
  changes.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  sections `6.2` and `6.5`: table metadata, generated columns,
  expression-index terms, DESC terms, and collation metadata.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema5.test`
  legacy constraint/default parsing shapes reused by table metadata.

Non-overlap:

- Extends the accepted PRAGMA/schema dynamic first/second/third-thousand
  coverage with a fourth thousand focused on attached-schema shadowing,
  `database_list`, `table_list`, table-valued PRAGMA function resolution,
  generated/expression-index metadata, and detach invalidation snapshots.
- Does not add metadata-only admission records, fake upstream script ids,
  WordPress-specific APIs, or dashboard-only movement.

Dependency closure:

- No new support component needed. The slice reuses existing
  `SQLiteAttachedSchemaCatalog`, `SQLitePragmaSchemaCatalog`,
  `SQLitePragmaRowCursor`, and `SQLiteSchemaRecord` behavior.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicFourthThousandTest.php`
  passed: `1 test files, 8403 assertions, 0 failures` with 1001 PASS lines.
