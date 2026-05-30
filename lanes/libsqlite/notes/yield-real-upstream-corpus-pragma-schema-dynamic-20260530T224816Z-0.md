# Real upstream PRAGMA/schema dynamic runtime corpus

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T224816Z-0`

Base accepted HEAD: `dc9a740fd34e07dba61e9143b3604d183ad170bf`

Upstream source files and sections:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-8.1.1` through `pragma-8.1.18`: `PRAGMA schema_version`, defensive write suppression, attached schema version state, and stale prepared statement invalidation semantics.
  - `pragma-8.2.1` through `pragma-8.2.15`: `PRAGMA user_version`, attached database isolation, transaction rollback restoration, and negative values.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma2.test`
  - `pragma2-4.1` through `pragma2-4.8`: default `cache_spill`, OFF/ON state, attached-database inheritance, and lock promotion.
  - `pragma2-5.1` through `pragma2-5.3`: boolean and numeric `cache_spill` forms.

Implementation:

- Added `SQLitePragmaRuntimeState`, a bounded native PHP state model for generic SQLite runtime PRAGMAs:
  - `schema_version`
  - `user_version`
  - `cache_size`
  - `cache_spill`
  - attached schema runtime state
  - transaction rollback/commit restoration
  - lock status transitions driven by dirty pages and spill thresholds
- Added `SQLiteRealUpstreamPragmaSchemaRuntimeDynamicTest.php` with 1000 generated dynamic behavior cases plus focused citation/smoke assertions, for 1006 focused PASS lines total.

Non-overlap:

- Does not modify the existing `SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php` catalog/introspection shard.
- Does not repeat existing PRAGMA schema catalog coverage for `table_info`, `table_xinfo`, `index_list`, `index_xinfo`, `foreign_key_list`, `table_list`, or table-valued PRAGMAs.
- Covers runtime PRAGMA state from `pragma.test` section 8 and `pragma2.test` cache-spill sections instead.
- Adds no WordPress-specific source names or APIs.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaRuntimeDynamicTest.php`
  - `1 test files, 7013 assertions, 0 failures`
  - 1006 PASS lines

Expected dashboard movement:

- Selected PHP PASS lines: `991889 -> 992895` (`+1006`)
- Mapped denominator coverage: unchanged at `1589 / 1589`

Dependency closure:

- No new support component is needed. The implementation reuses lane-local native PHP state modeling only.
