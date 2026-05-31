# Real Upstream Corpus: PRAGMA Schema Dynamic Table-Valued

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T050125Z-0`

Base accepted HEAD: `7d59ee97325649cafd2449deb321f30571bf474f`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-6.5.1b`: `index_xinfo()` auxiliary rowid output
  - `pragma-6.5.1c`: `index_info()` key-column rank output
  - `pragma-6.6.1` through `pragma-6.6.4`: temp/main table_info shadowing
  - `pragma-6.7`: default expression and type preservation
  - `pragma-6.8`: composite primary-key ordinal preservation
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  - `5.0`: default values with comments
  - `6.0` through `7.3`: table-valued PRAGMA joins
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma5.test`
  - `1.0` through `3.1`: virtual PRAGMA table metadata

Implemented behavior:

- Added `SQLiteRealUpstreamPragmaSchemaDynamicTableValuedTest.php` with
  1000 distinct dynamic TestRunner cases plus one source-citation case.
- Fixed `SQLitePragmaSchemaCatalog` default-value parsing so top-level
  trailing comments are not included in PRAGMA `table_info` `dflt_value`,
  including parenthesized defaults.
- Covered table-valued PRAGMA schema resolution, generated-column hidden
  flags, `index_info`/`index_xinfo` row shapes, virtual PRAGMA table metadata,
  `database_list` attach/detach order, and schema-cache invalidation.

Verification:

- Red-first focused run before the fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicTableValuedTest.php`
  failed with 1000 failures because parenthesized default values retained
  trailing `--` comments.
- Passing focused run after the fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicTableValuedTest.php`
  passed with `1 test files, 37003 assertions, 0 failures` and `1001` PASS
  lines.

Expected dashboard movement if accepted:

- `phpPass`: `2202926 -> 2203927` (`+1001`)
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`

Dependency closure:

- No new support component is needed. The slice reuses existing native
  `SQLiteAttachedSchemaCatalog`, `SQLitePragmaSchemaCatalog`, and
  `SQLitePragmaRowCursor` behavior.
