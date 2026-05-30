# Real upstream corpus: PRAGMA/schema dynamic fifth thousand

Added `SQLiteRealUpstreamPragmaSchemaDynamicFifthThousandTest.php`, a
1,001-case focused PHP TestRunner batch backed by hydrated upstream SQLite
schema/PRAGMA files:

- `test/pragma.test`: `pragma-6.2` through `pragma-6.8` schema-query PRAGMAs.
- `test/pragma4.test`: `pragma-4.1` through `pragma-7.3` table-valued PRAGMA
  argument and rowset behavior.
- `test/pragma5.test`: `1.0` through `3.1` `table_list` object flags.
- `test/pragma6.test`: `1.0` through `1.2` runtime list rowset source sections
  cited for continuity with neighboring accepted PRAGMA batches.
- `test/schema.test`: `schema-1.*`, `schema-4.*`, `schema-5.*`, `schema-9.*`,
  and `schema-10.*` sqlite_schema and attached schema invalidation behavior.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicFifthThousandTest.php`
  passed with `1 test files, 8203 assertions, 0 failures`.
- Focused PASS-line growth: `+1001` distinct TestRunner cases.
- Mapped denominator growth: none; the upstream denominator is already fully
  mapped, this is PASS-line growth only.

Non-overlap:

- Uses a fresh `real upstream pragma schema fifth thousand` namespace and
  generic `fifth_schema_*` application table/index names.
- Does not repeat accepted first/second/third/fourth-thousand, wide-batch,
  wide-batch follow-up, schema3, schema4, schema5, schema6, data-version, or
  cache-spill PRAGMA dynamic files.
- Does not add generated fake upstream script ids, metadata-only admission
  rows, WordPress-specific APIs, or new `wp_*` scenarios.

Dependency closure:

- No new support component is required. This reuses the existing
  `SQLiteAttachedSchemaCatalog`, `SQLitePragmaSchemaCatalog`, row cursor, schema
  record parser, attached-schema invalidation, and table-valued PRAGMA support.
