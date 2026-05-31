# Real Upstream PRAGMA Schema Dynamic Slice

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T013821Z-0`

Accepted base: `472430c1daaad1016852e97d68cabd3ea687d289`

Upstream source files and sections:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-6.2.*`: `PRAGMA table_info` declared types, defaults, and composite primary-key ordinals.
  - `pragma-6.5.*`: `PRAGMA index_info` and `PRAGMA index_xinfo`, including auxiliary rowid columns.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  - `pragma4-5.0`: defaults with comments.
  - `pragma4-6.0`: schema-qualified `pragma_foreign_key_list()` joins through table-valued PRAGMA functions.
  - `pragma4-6.1` through `pragma4-6.3`: `PRAGMA table_list` tolerates a view whose SQL references a missing function and reports zero columns.
  - `pragma4-7.0` through `pragma4-7.3`: `pragma_table_info()` rowsets remain usable through join-style row comparisons.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma5.test`
  - `pragma5-1.0` through `pragma5-3.1`: `pragma_function_list`, `pragma_module_list`, and `pragma_pragma_list` metadata.

Implementation movement:

- Extended `SQLiteRealUpstreamPragmaSchemaDynamicShadowingTest.php` with 250 dynamic variants across four new behavior cases.
- Fixed `SQLiteAttachedSchemaCatalog` attached-schema dispatch for `pragma_list`, matching existing function/module/collation list routing.

Focused evidence:

- Before: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicShadowingTest.php`
  - `1 test files, 8504 assertions, 0 failures`
  - 1001 PASS lines.
- Red intermediate: same command failed 250 new `pragma_list` attached-schema metadata cases with `Unhandled match case of type string`.
- After: same command passed.
  - `1 test files, 17755 assertions, 0 failures`
  - 2001 PASS lines.

Countable movement:

- `+1000` focused PASS cases.
- `+9251` focused behavior assertions.
- Lane-local `phpPass` projection moves from `1524483` to `1525483`.

Non-overlap:

- Does not repeat the existing shadowing cases for pragma4 4.* through 7.*, pragma5 table_list flags, or schema.test attach/detach invalidation.
- Adds distinct upstream coverage for table-info default/comment parsing, composite PK ordinals, index_xinfo auxiliary rowid rows, schema-qualified FK list rows, PRAGMA virtual-table metadata, and corrupt-view table_list tolerance.

Dependency closure:

- No new support component needed.
- Reuses the lane-local schema record parser, attached-schema catalog, PRAGMA row cursor, table-valued PRAGMA dispatch, index metadata parser, and foreign-key parser.
