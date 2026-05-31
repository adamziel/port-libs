# Real Upstream Corpus: PRAGMA Schema Version State

Micro-slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T002020Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- `pragma-8.1.1` through `pragma-8.1.18`: `PRAGMA schema_version` read/write, defensive-mode write suppression, attached schema isolation, and schema reload pressure.
- `pragma-8.2.1` through `pragma-8.2.15`: `PRAGMA user_version` read/write, attached schema isolation, rollback restoration, and negative values.

Ported behavior:

- Added `SQLiteRealUpstreamPragmaSchemaDynamicVersionStateTest.php`.
- The test uses `SQLitePragmaRuntimeState` to exercise generic main/temp/attached schema runtime PRAGMA state with 250 dynamic variants.
- Focused PASS growth: 1,001 TestRunner PASS cases and 7,255 behavior assertions.

Non-overlap:

- This does not repeat accepted PRAGMA data_version, cache_spill, schema catalog, table-valued PRAGMA, pragma6 integrity, or schema6 equivalence batches.
- It specifically covers `schema_version` and `user_version` state transitions from upstream `pragma.test` section 8.

Dependency closure:

- No new support component is needed. The existing bounded `SQLitePragmaRuntimeState` model is reused.
