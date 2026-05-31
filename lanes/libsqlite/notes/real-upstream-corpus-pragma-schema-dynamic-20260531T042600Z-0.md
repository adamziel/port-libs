# real-upstream-corpus-pragma-schema-dynamic-20260531T042600Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
- Ported sections:
  - `pragma4-4.1.1` through `pragma4-4.1.6`: direct `PRAGMA table_info` main/attached table resolution before and after external drops.
  - `pragma4-4.2.1` through `pragma4-4.2.6`: `pragma_table_info()` table-valued rowsets across the same drop/reparse boundary.
  - `pragma4-4.3.1` through `pragma4-4.3.6`: `pragma_index_info()` main/attached index rowsets before and after external drops.
  - `pragma4-4.4.0` through `pragma4-4.4.6`: `pragma_index_list()` created-index rowsets and empty rowsets after drop.
  - `pragma4-4.5.0` through `pragma4-4.5.1`: `pragma_foreign_key_list()` main/attached child-to-parent mappings.

## Handoff

- Added `SQLiteRealUpstreamPragmaSchemaDynamicDropMatrixTest.php`.
- Focused count: `1501` distinct TestRunner PASS cases, `9253` assertions.
- Expected selected PASS movement if accepted: `2070862 -> 2072363`.
- Mapped denominator movement: none; upstream inventory is already mapped at `1589 / 1589`.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicDropMatrixTest.php`
  - `1 test files, 9253 assertions, 0 failures`
  - `1501` PASS lines.

## Non-Overlap

This does not repeat accepted `SQLiteRealUpstreamPragmaSchemaDynamicJoinMatrixTest.php` or `SQLiteRealUpstreamPragmaSchemaDynamicSchema6EquivalenceTest.php`. Those cover dynamic PRAGMA joins and schema6 equivalence. This slice covers upstream `pragma4.test` drop/reparse invalidation for direct and table-valued schema PRAGMAs.

## Dependency Closure

No new support component is needed. The slice reuses the existing `SQLiteAttachedSchemaCatalog`, `SQLitePragmaSchemaCatalog`, and `SQLiteSchemaRecord` bounded in-memory schema primitives.
